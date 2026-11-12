import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Plus, Phone, MessageSquare, Bot, BarChart3, Settings, CheckCircle, XCircle, AlertCircle, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import AssignAIAgentModal from '@/components/sms/assign-ai-agent-modal';
import { toast } from 'sonner';
import axios from 'axios';

interface PhoneNumber {
  id: number;
  number?: string;
  phone_number?: string;
  friendly_name?: string | null;
  status?: string;
  capabilities?: {
    sms?: boolean;
    mms?: boolean;
    voice?: boolean;
  };
  ai_agent?: {
    id: number;
    name: string;
  } | null;
  conversations_count?: number;
  has_agent?: boolean;
}

interface AIAgent {
  id: number;
  name: string;
  description: string;
}

interface Props {
  phoneNumbers: PhoneNumber[];
  aiAgents: AIAgent[];
}

export default function Sms({ phoneNumbers, aiAgents }: Props) {
  const [selectedPhone, setSelectedPhone] = useState<PhoneNumber | null>(null);
  const [showAssignModal, setShowAssignModal] = useState(false);
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
  {
    title: t('nav.home'),
    href: dashboard().url,
  },
  {
    title: t('sms.title'),
  },
];

  const handleAssignAgent = (phone: PhoneNumber) => {
    setSelectedPhone(phone);
    setShowAssignModal(true);
  };

  const formatPhoneNumber = (phone: string | undefined | null) => {
    if (!phone) return 'N/A';
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 11 && cleaned[0] === '1') {
      return `+${cleaned[0]} (${cleaned.slice(1, 4)}) ${cleaned.slice(4, 7)}-${cleaned.slice(7)}`;
    }
    return phone;
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('sms.title')} />

      <div className="container mx-auto p-6 space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('sms.title')}</h1>
            <p className="text-muted-foreground mt-1">
              {t('sms.description')}
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => router.visit('/sms/compose')}>
              <MessageSquare className="mr-2 h-4 w-4" />
              {t('sms.new_message')}
            </Button>
          </div>
        </div>

        {/* Summary Stats */}
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('sms.total_numbers')}</CardTitle>
              <Phone className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{phoneNumbers.length}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('sms.with_ai')}</CardTitle>
              <Bot className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {phoneNumbers.filter(p => p.has_agent === true).length}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('sms.active_conversations')}</CardTitle>
              <MessageSquare className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">
                {phoneNumbers.reduce((sum, p) => sum + (p.conversations_count || 0), 0)}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-foreground">{t('sms.capable_numbers')}</CardTitle>
              <BarChart3 className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-foreground">
                {phoneNumbers.filter(p => p.status === 'assigned' || p.status === undefined).length}
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Phone Numbers List */}
        <div className="space-y-4">
          <h2 className="text-xl font-semibold text-foreground">{t('sms.your_numbers')}</h2>

          {phoneNumbers.length === 0 ? (
            <Card className="border-border bg-card">
              <CardContent className="flex flex-col items-center justify-center py-12">
                <Phone className="h-12 w-12 text-muted-foreground mb-4" />
                <h3 className="text-lg font-semibold text-foreground mb-2">{t('sms.no_numbers')}</h3>
                <p className="text-muted-foreground text-center mb-4">
                  {t('sms.no_numbers_description')}
                </p>
              </CardContent>
            </Card>
          ) : (
            <div className="grid gap-4">
              {phoneNumbers.map((phone) => (
                <Card key={phone.id} className="border-border bg-card">
                  <CardContent className="p-6">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-3 mb-2">
                          <Phone className="h-5 w-5 text-primary" />
                          <h3 className="text-lg font-semibold text-foreground">
                            {formatPhoneNumber(phone.number)}
                          </h3>
                          <Badge variant={phone.status === 'assigned' ? 'default' : 'secondary'}>
                            {phone.status || 'unknown'}
                          </Badge>
                          {phone.capabilities?.sms && (
                            <Badge variant="outline" className="gap-1 bg-green-600/10 text-green-600 dark:bg-green-400/10 dark:text-green-400">
                              <CheckCircle className="h-3 w-3" />
                              SMS Enabled
                            </Badge>
                          )}
                          {phone.capabilities?.mms && (
                            <Badge variant="outline" className="gap-1 bg-blue-600/10 text-blue-600 dark:bg-blue-400/10 dark:text-blue-400">
                              MMS
                            </Badge>
                          )}
                        </div>

                        {phone.friendly_name && (
                          <p className="text-sm text-muted-foreground mb-3">
                            {phone.friendly_name}
                          </p>
                        )}

                        <div className="flex items-center gap-6 mt-3">
                          {phone.ai_agent ? (
                            <div className="flex items-center gap-2">
                              <Bot className="h-4 w-4 text-green-600 dark:text-green-400" />
                              <span className="text-sm font-medium text-foreground">
                                AI: {phone.ai_agent.name}
                              </span>
                            </div>
                          ) : (
                            <div className="flex items-center gap-2">
                              <Bot className="h-4 w-4 text-orange-600 dark:text-orange-400" />
                              <span className="text-sm text-muted-foreground">
                                {t('sms.no_agent')}
                              </span>
                            </div>
                          )}

                          <div className="flex items-center gap-2">
                            <MessageSquare className="h-4 w-4 text-muted-foreground" />
                            <span className="text-sm text-foreground">
                              {phone.conversations_count || 0} conversations
                            </span>
                          </div>
                        </div>
                      </div>

                      <div className="flex items-center gap-2 flex-wrap justify-end">
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() =>
                            router.visit(`/sms/conversations/${phone.id}`)
                          }
                        >
                          <MessageSquare className="h-4 w-4 mr-2" />
                          {t('sms.conversations')}
                        </Button>

                        <Button
                          variant={phone.ai_agent ? 'outline' : 'default'}
                          size="sm"
                          onClick={() => handleAssignAgent(phone)}
                        >
                          <Bot className="h-4 w-4 mr-2" />
                          {phone.ai_agent ? t('sms.change_agent') : t('sms.assign_agent')}
                        </Button>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ))}
            </div>
          )}
        </div>

        {/* Quick Actions */}
        <div className="grid gap-4 md:grid-cols-3">
          <Card className="cursor-pointer hover:bg-accent/50 transition-colors border-border bg-card"
                onClick={() => router.visit('/sms/conversations')}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-foreground">
                <MessageSquare className="h-5 w-5" />
                View All Conversations
              </CardTitle>
              <CardDescription>
                See all SMS conversations across all numbers
              </CardDescription>
            </CardHeader>
          </Card>

          <Card className="cursor-pointer hover:bg-accent/50 transition-colors border-border bg-card"
                onClick={() => router.visit('/sms/templates')}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-foreground">
                <MessageSquare className="h-5 w-5" />
                SMS Templates
              </CardTitle>
              <CardDescription>
                Create and manage reusable message templates
              </CardDescription>
            </CardHeader>
          </Card>

          <Card className="cursor-pointer hover:bg-accent/50 transition-colors border-border bg-card"
                onClick={() => router.visit('/sms/analytics')}>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-foreground">
                <BarChart3 className="h-5 w-5" />
                SMS Analytics
              </CardTitle>
              <CardDescription>
                View performance metrics and insights
              </CardDescription>
            </CardHeader>
          </Card>
        </div>
      </div>

      {/* Assign AI Agent Modal */}
      {selectedPhone && (
        <AssignAIAgentModal
          open={showAssignModal}
          onClose={() => {
            setShowAssignModal(false);
            setSelectedPhone(null);
          }}
          phoneNumber={selectedPhone}
          aiAgents={aiAgents}
        />
      )}
    </AppLayout>
  );
}
