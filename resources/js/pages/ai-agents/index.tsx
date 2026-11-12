import { useState, useEffect } from 'react';
import { Plus, Bot, Phone, TrendingUp, DollarSign, Search, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import axios from 'axios';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import type { AiAgent, AiAgentStats } from '@/types/ai-agent';
import { useTranslation } from 'react-i18next';
import { useFormatCurrency } from '@/hooks/use-currency';

export default function AiAgentsDashboard() {
  const { t } = useTranslation();
  const { formatCurrency } = useFormatCurrency();
  const [agents, setAgents] = useState<AiAgent[]>([]);
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState<any>({});
  const [searchTerm, setSearchTerm] = useState('');

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('nav.home'),
      href: dashboard().url,
    },
    {
      title: t('nav.conversational_ai'),
      href: '/ai-agents',
    },
    {
      title: t('nav.ai_agents'),
    },
  ];

  const filteredAgents = agents.filter(agent => {
    if (!searchTerm) return true;
    const search = searchTerm.toLowerCase();
    return (
      agent.name.toLowerCase().includes(search) ||
      agent.description?.toLowerCase().includes(search) ||
      agent.type.toLowerCase().includes(search) ||
      agent.model.toLowerCase().includes(search) ||
      agent.phone_number?.toLowerCase().includes(search)
    );
  });

  const handleClearSearch = () => {
    setSearchTerm('');
  };

  useEffect(() => {
    loadAgents();
  }, []);

  const loadAgents = async () => {
    try {
      const response = await axios.get('/api/v1/ai-agents');
      setAgents(response.data.data || []);
    } catch (error) {
      console.error('Failed to load AI agents:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadAgentStats = async (agentId: number) => {
    try {
      const response = await axios.get(`/api/v1/ai-agents/${agentId}`);
      setStats(prev => ({ ...prev, [agentId]: response.data.stats }));
    } catch (error) {
      console.error('Failed to load agent stats:', error);
    }
  };

  useEffect(() => {
    agents.forEach(agent => {
      loadAgentStats(agent.id);
    });
  }, [agents]);

  if (loading) {
    return (
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title={t('nav.ai_agents')} />
        <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
          <div className="flex items-center justify-center h-96">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
          </div>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('nav.ai_agents')} />
      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <Heading
            title={t('nav.ai_agents')}
            description={t('ai_agents.index_description')}
          />
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            {agents.length > 0 && (
              <div className="relative flex-1 sm:w-80">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder={t('ai_agents.search_agents')}
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-9 pr-9"
                />
                {searchTerm && (
                  <button
                    onClick={handleClearSearch}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                  >
                    <X className="h-4 w-4" />
                  </button>
                )}
              </div>
            )}
            <Button onClick={() => router.visit('/ai-agents/create')} className="whitespace-nowrap">
              <Plus className="mr-2 h-4 w-4" />
              {t('ai_agents.create_agent')}
            </Button>
          </div>
        </div>

      {agents.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-16">
            <div className="rounded-full bg-primary/10 p-6 mb-4">
              <Bot className="h-12 w-12 text-primary" />
            </div>
            <h3 className="text-xl font-semibold mb-2">{t('ai_agents.no_agents_yet')}</h3>
            <p className="text-muted-foreground mb-6 text-center max-w-sm">
              {t('ai_agents.no_agents_description')}
            </p>
            <Button onClick={() => router.visit('/ai-agents/create')} size="lg">
              <Plus className="mr-2 h-4 w-4" />
              {t('ai_agents.create_first_agent')}
            </Button>
          </CardContent>
        </Card>
      ) : filteredAgents.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-16">
            <div className="rounded-full bg-muted p-6 mb-4">
              <Search className="h-12 w-12 text-muted-foreground" />
            </div>
            <h3 className="text-xl font-semibold mb-2">{t('ai_agents.no_agents_found')}</h3>
            <p className="text-muted-foreground mb-6 text-center max-w-sm">
              {t('ai_agents.no_agents_found_description', { search: searchTerm })}
            </p>
            <Button onClick={handleClearSearch} variant="outline">
              {t('ai_agents.clear_search')}
            </Button>
          </CardContent>
        </Card>
      ) : (
        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {filteredAgents.map((agent) => (
            <Card
              key={agent.id}
              className="cursor-pointer hover:shadow-lg transition-shadow"
              onClick={() => router.visit(`/ai-agents/${agent.id}`)}
            >
              <CardHeader>
                <div className="flex items-start justify-between">
                  <div className="flex items-center space-x-2">
                    <Bot className="h-5 w-5" />
                    <CardTitle className="text-lg">{agent.name}</CardTitle>
                  </div>
                  <Badge variant={agent.active ? 'default' : 'secondary'}>
                    {agent.active ? t('common.active') : t('common.inactive')}
                  </Badge>
                </div>
                <CardDescription className="line-clamp-2">
                  {agent.description || t('ai_agents.no_description')}
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">{t('common.type')}</span>
                    <Badge variant="outline">{agent.type}</Badge>
                  </div>
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">{t('ai_agents.model')}</span>
                    <span className="font-medium">{agent.model}</span>
                  </div>
                  <div className="flex items-center justify-between text-sm">
                    <span className="text-muted-foreground">{t('common.voice')}</span>
                    <span className="font-medium">{agent.voice}</span>
                  </div>
                  {agent.phone_number && (
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-muted-foreground">{t('ai_agents.phone_number')}</span>
                      <Badge variant="outline" className="font-mono">
                        <Phone className="mr-1 h-3 w-3" />
                        {agent.phone_number}
                      </Badge>
                    </div>
                  )}

                  {stats[agent.id] && (
                    <>
                      <div className="border-t pt-3 mt-3">
                        <div className="grid grid-cols-2 gap-2">
                          <div className="flex items-center space-x-2">
                            <Phone className="h-4 w-4 text-muted-foreground" />
                            <div>
                              <p className="text-xs text-muted-foreground">{t('ai_agents.total_calls')}</p>
                              <p className="font-semibold">{stats[agent.id].total_calls || 0}</p>
                            </div>
                          </div>
                          <div className="flex items-center space-x-2">
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                            <div>
                              <p className="text-xs text-muted-foreground">{t('ai_agents.success_rate')}</p>
                              <p className="font-semibold">{stats[agent.id].success_rate || 0}%</p>
                            </div>
                          </div>
                          <div className="flex items-center space-x-2">
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                            <div>
                              <p className="text-xs text-muted-foreground">{t('ai_agents.total_cost')}</p>
                              <p className="font-semibold">{formatCurrency(stats[agent.id].total_cost || 0)}</p>
                            </div>
                          </div>
                          <div className="flex items-center space-x-2">
                            <div>
                              <p className="text-xs text-muted-foreground">{t('ai_agents.avg_duration')}</p>
                              <p className="font-semibold">{Math.round(stats[agent.id].average_duration || 0)}s</p>
                            </div>
                          </div>
                        </div>
                      </div>
                    </>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>
      )}
      </div>
    </AppLayout>
  );
}
