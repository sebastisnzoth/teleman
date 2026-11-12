import { useState, useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Send, Bot, User, Phone, MessageSquare } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';

interface Message {
  id: number;
  conversation_id: number;
  direction: 'inbound' | 'outbound';
  message_body: string;
  sender_phone: string;
  receiver_phone: string;
  status: string;
  ai_generated: boolean;
  created_at: string;
}

interface Conversation {
  id: number;
  contact_phone: string;
  contact_name: string | null;
  status: string;
  last_message_at: string;
  unread_count: number;
  messages_count: number;
  phone_number: {
    id: number;
    number: string;
    friendly_name: string | null;
  };
  ai_agent: {
    id: number;
    name: string;
  } | null;
}

interface Props {
  conversations: {
    data: Conversation[];
  };
  currentPhone?: any;
}

export default function SmsConversations({ conversations, currentPhone }: Props) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
  {
    title: t('sms.home'), href: dashboard().url,
  },
  {
    title: t('sms.title'), href: '/sms',
  },
  {
    title: 'Conversations',
  },
];
  const [selectedConversation, setSelectedConversation] = useState<Conversation | null>(null);
  const [messages, setMessages] = useState<Message[]>([]);
  const [loadingMessages, setLoadingMessages] = useState(false);
  const [newMessage, setNewMessage] = useState('');
  const [sending, setSending] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  useEffect(() => {
    if (conversations.data.length > 0 && !selectedConversation) {
      handleSelectConversation(conversations.data[0]);
    }
  }, [conversations]);

  const handleSelectConversation = async (conversation: Conversation) => {
    setSelectedConversation(conversation);
    setLoadingMessages(true);

    try {
      const response = await axios.get(`/sms/conversations/${conversation.id}/messages`);
      setMessages(response.data.messages || []);
    } catch (error) {
      console.error('Failed to load messages:', error);
    } finally {
      setLoadingMessages(false);
    }
  };

  const handleSendMessage = async () => {
    if (!newMessage.trim() || !selectedConversation) return;

    setSending(true);

    try {
      const response = await axios.post(
        `/sms/conversations/${selectedConversation.id}/send`,
        { message: newMessage }
      );

      if (response.data.success) {
        setMessages([...messages, response.data.message]);
        setNewMessage('');
      }
    } catch (error) {
      console.error('Failed to send message:', error);
      alert('Failed to send message');
    } finally {
      setSending(false);
    }
  };

  const formatPhoneNumber = (phone: string) => {
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 11 && cleaned[0] === '1') {
      return `+${cleaned[0]} (${cleaned.slice(1, 4)}) ${cleaned.slice(4, 7)}-${cleaned.slice(7)}`;
    }
    return phone;
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('sms.sms_conversations')} />

      <div className="container mx-auto p-6 space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight text-foreground">SMS Conversations</h1>
            <p className="text-muted-foreground mt-1">
              {currentPhone
                ? `Messages for ${currentPhone.number}`
                : 'All SMS conversations'}
            </p>
          </div>
          <Button onClick={() => router.visit('/sms/compose')}>
            <MessageSquare className="mr-2 h-4 w-4" />
            New Message
          </Button>
        </div>

        <div className="grid grid-cols-12 gap-6 h-[calc(100vh-16rem)]">
          {/* Conversation List */}
          <Card className="col-span-4 border-border bg-card">
            <CardHeader>
              <CardTitle className="text-foreground">{t('sms.conversations')}</CardTitle>
              <CardDescription>
                {conversations.data.length} active conversations
              </CardDescription>
            </CardHeader>
            <CardContent className="p-0">
              <div className="divide-y divide-border">
                {conversations.data.length === 0 ? (
                  <div className="p-8 text-center">
                    <MessageSquare className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                    <h3 className="text-lg font-semibold text-foreground mb-2">No Conversations</h3>
                    <p className="text-muted-foreground">No SMS conversations yet</p>
                  </div>
                ) : (
                  conversations.data.map((conv) => (
                    <div
                      key={conv.id}
                      className={`p-4 cursor-pointer hover:bg-muted/50 transition-colors ${
                        selectedConversation?.id === conv.id ? 'bg-muted' : ''
                      }`}
                      onClick={() => handleSelectConversation(conv)}
                    >
                      <div className="flex items-start gap-3">
                        <div className="flex-shrink-0">
                          <Phone className="h-10 w-10 rounded-full bg-primary/10 p-2 text-primary" />
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center justify-between mb-1">
                            <h4 className="font-semibold text-sm text-foreground truncate">
                              {conv.contact_name || formatPhoneNumber(conv.contact_phone)}
                            </h4>
                            {conv.unread_count > 0 && (
                              <Badge variant="default">{conv.unread_count}</Badge>
                            )}
                          </div>
                          <div className="flex items-center justify-between text-xs">
                            {conv.ai_agent ? (
                              <div className="flex items-center gap-1 text-green-600 dark:text-green-400">
                                <Bot className="h-3 w-3" />
                                <span>{conv.ai_agent.name}</span>
                              </div>
                            ) : (
                              <span className="text-muted-foreground">Manual</span>
                            )}
                            {conv.last_message_at && (
                              <span className="text-muted-foreground">
                                {formatDistanceToNow(new Date(conv.last_message_at), {
                                  addSuffix: true,
                                })}
                              </span>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </CardContent>
          </Card>

          {/* Message Thread */}
          <Card className="col-span-8 flex flex-col border-border bg-card">
            {selectedConversation ? (
              <>
                <CardHeader className="border-b border-border">
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle className="text-foreground">
                        {selectedConversation.contact_name ||
                          formatPhoneNumber(selectedConversation.contact_phone)}
                      </CardTitle>
                      <CardDescription>
                        {selectedConversation.phone_number.friendly_name ||
                          selectedConversation.phone_number.number}
                        {selectedConversation.ai_agent && (
                          <span className="ml-2 text-green-600 dark:text-green-400">
                            • AI Agent: {selectedConversation.ai_agent.name}
                          </span>
                        )}
                      </CardDescription>
                    </div>
                  </div>
                </CardHeader>

                {/* Messages */}
                <CardContent className="flex-1 overflow-y-auto p-6 space-y-4 bg-background">
                  {loadingMessages ? (
                    <div className="flex items-center justify-center h-full">
                      <div className="text-muted-foreground">Loading messages...</div>
                    </div>
                  ) : messages.length === 0 ? (
                    <div className="flex flex-col items-center justify-center h-full">
                      <MessageSquare className="h-12 w-12 text-muted-foreground mb-4" />
                      <h3 className="text-lg font-semibold text-foreground mb-2">No Messages</h3>
                      <p className="text-muted-foreground">Start the conversation by sending a message</p>
                    </div>
                  ) : (
                    <>
                      {messages.map((msg) => (
                        <div
                          key={msg.id}
                          className={`flex ${
                            msg.direction === 'inbound' ? 'justify-start' : 'justify-end'
                          }`}
                        >
                          <div
                            className={`max-w-[70%] rounded-lg px-4 py-2 ${
                              msg.direction === 'inbound'
                                ? 'bg-muted text-foreground'
                                : 'bg-primary text-primary-foreground'
                            }`}
                          >
                            <div className="flex items-center gap-2 mb-1">
                              {msg.direction === 'inbound' ? (
                                <User className="h-3 w-3" />
                              ) : msg.ai_generated ? (
                                <Bot className="h-3 w-3" />
                              ) : (
                                <User className="h-3 w-3" />
                              )}
                              <span className="text-xs opacity-70">
                                {formatDistanceToNow(new Date(msg.created_at), { addSuffix: true })}
                              </span>
                            </div>
                            <p className="text-sm">{msg.message_body}</p>
                          </div>
                        </div>
                      ))}
                      <div ref={messagesEndRef} />
                    </>
                  )}
                </CardContent>

                {/* Message Input */}
                <div className="border-t border-border p-4 bg-card">
                  <div className="flex gap-2">
                    <Input
                      placeholder="Type a message..."
                      value={newMessage}
                      onChange={(e) => setNewMessage(e.target.value)}
                      onKeyPress={(e) => {
                        if (e.key === 'Enter' && !e.shiftKey) {
                          e.preventDefault();
                          handleSendMessage();
                        }
                      }}
                      disabled={sending}
                      className="bg-background text-foreground"
                    />
                    <Button onClick={handleSendMessage} disabled={sending || !newMessage.trim()}>
                      <Send className="h-4 w-4" />
                    </Button>
                  </div>
                  <p className="text-xs text-muted-foreground mt-2">
                    Press Enter to send, Shift+Enter for new line
                  </p>
                </div>
              </>
            ) : (
              <div className="flex items-center justify-center h-full">
                <div className="text-center text-muted-foreground">
                  <Phone className="h-12 w-12 mx-auto mb-4 opacity-50" />
                  <p className="text-foreground">Select a conversation to view messages</p>
                </div>
              </div>
            )}
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}
