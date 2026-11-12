# 📞 Teleman - Telemarketing VoIP SaaS Application

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-slimgreen.svg)](https://www.php.net/downloads.php)
[![Laravel](https://img.shields.io/badge/Laravel-12-ff69b4.svg)](https://laravel.com/)
[![Node.js](https://img.shields.io/badge/Node.js-18%2B-green.svg)](https://nodejs.org/)

Uma plataforma completa de telemarketing VoIP com todas as funcionalidades necessárias para empresas de atendimento ao cliente, vendas e CRM integrado.

## 🚀 Funcionalidades Principais

- **Telefonia VoIP** - Integração com Twilio, Plivo e provedores SIP
- **Campanhas de Telemarketing** - Sequências de ligações automatizadas
- **CRM Integrado** - Gestão de contatos, histórico de ligações e interações
- **IA Assistida** - Agentes de IA para conversas automatizadas
- **Pagamento Integrado** - Stripe, PayPal, Razorpay e criptomoedas
- **Relatórios em Tempo Real** - Dashboard completo com métricas

## 🛠️ Tecnologias

| Componente | Versão |
|------------|--------|
| **PHP** | 8.2+ |
| **Laravel** | 12.x |
| **Node.js** | 18+ |
| **React** | 19.x |
| **Inertia.js** | 2.x |
| **Tailwind CSS** | 4.x |
| **Vite** | 5.x |

## 🚀 Como Executar Localmente

### Requisitos
- PHP 8.2 ou superior
- Composer 2.x
- Node.js 18.x
- MySQL ou SQLite

### Passos

```bash
# 1. Clonar o repositório
git clone https://github.com/sebastisnzoth/teleman.git
cd teleman

# 2. Instalar dependências PHP
composer install

# 3. Instalar dependências Node.js
npm install

# 4. Configurar variáveis de ambiente
cp .env.example .env
php artisan key:generate

# 5. Executar migrations
php artisan migrate

# 6. Build do frontend
npm run build

# 7. Iniciar servidor
php artisan serve

# Acesse: http://localhost:8000
```

### Desenvolvimento

```bash
# Iniciar modo desenvolvimento
composer dev

# O simplesmente:
php artisan serve
npm run dev
```

## 🌐 Desenvolvimento com lamp.endor.dev

Este projeto está configurado para rodar no ambiente **endor.dev/s/lamp**:

```bash
# Acesso via endor.dev:
https://teleman.sebastisnzoth.teleman.lamp.endor.dev

# Ou clonar e executar localmente:
git clone https://github.com/sebastisnzoth/teleman.git
cd teleman
./setup-local.sh  # Script de instalação automática
```

## 📁 Estrutura do Projeto

```
teleman/
├── app/              # Código principal da aplicação
│   ├── Http/         # Controllers e Middleware
│   ├── Models/       # Models Eloquent
│   ├── Policies/     # Políticas de acesso
│   └──Providers/     # Service Providers
├── bootstrap/        # Bootstraps da aplicação
├── config/           # Arquivos de configuração
├── database/         # Migrations e Seeders
├── public/           # Arquivos públicos
├── resources/        # Views, CSS, JS
├── routes/           # Definição de rotas
├── storage/          # Storage da aplicação
└── tests/            # Testes automatizados
```

## ⚙️ Configuração

### Configurações Necessárias no .env

```env
APP_NAME=Teleman
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=teleman
DB_USERNAME=root
DB_PASSWORD=

# Twilio (Telefonia)
TELEPHONY_PROVIDER=twilio
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_PHONE_NUMBER=

# Stripe (Pagamentos)
STRIPE_SECRET_KEY=
STRIPE_PUBLIC_KEY=
STRIPE_WEBHOOK_SECRET=
```

## 🤝 Contribuição

1. Fork o repositório
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas alterações (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📄 Licença

Este projeto está licenciado sob a MIT License - veja o arquivo [LICENSE](LICENSE) para mais detalhes.

## 📞 Suporte

Para dúvidas técnicas ou suporte, entre em contato através das issues do repositório.

---

[Criado com ❤️ por Sebastião Nzoth](https://github.com/sebastisnzoth)
EOF

echo "✓ README.md atualizado"
head -30 README.md
