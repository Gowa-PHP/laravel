<div align="center">
  <img src="art/banner.png" alt="gowa-laravel Banner" width="100%" max-width="800">

  # gowa-php/laravel

  **Integração do GOWA para Laravel — Facade, Channel de Notificação, Roteamento de Webhooks e Models Eloquent**

  [![Última Versão Estável](https://img.shields.io/packagist/v/gowa-php/laravel.svg?style=flat-square)](https://packagist.org/packages/gowa-php/laravel)
  [![Total de Downloads](https://img.shields.io/packagist/dt/gowa-php/laravel.svg?style=flat-square)](https://packagist.org/packages/gowa-php/laravel)
  [![Licença](https://img.shields.io/badge/licen%C3%A7a-MIT-blue.svg?style=flat-square)](LICENSE)
  [![Versão do PHP](https://img.shields.io/badge/PHP-%3E%3D%208.2-777BB4.svg?style=flat-square)](https://php.net)
  [![Laravel](https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-FF2D20.svg?style=flat-square)](https://laravel.com)

</div>

---

> 🇺🇸 Para ler a documentação em Inglês, acesse [README.md](README.md).

---

## ⚡ Agradecimentos e Dependências

Este pacote interage com o ecossistema backend em Go criado pela comunidade open-source:

- **[whatsmeow](https://go.mau.fi/whatsmeow)** — Biblioteca Go criada por [Tulir Asokan](https://github.com/tulir) que faz a engenharia reversa do protocolo WebSocket do WhatsApp Web Multi-Device e criptografia Signal.
- **[go-whatsapp-web-multidevice (GOWA)](https://github.com/aldinokemal/go-whatsapp-web-multidevice)** — O servidor API REST criado por [Aldino Kemal](https://github.com/aldinokemal) que expõe o `whatsmeow` via HTTP e Webhooks.
- **[gowa-php/sdk](https://packagist.org/packages/gowa-php/sdk)** — O SDK PHP base utilizado por este pacote de integração.

---

## Requisitos

- PHP >= 8.2
- Laravel 10, 11 ou 12
- [`gowa-php/sdk`](https://packagist.org/packages/gowa-php/sdk) ^1.0
- Uma instância ativa do servidor API REST **[GOWA (go-whatsapp-web-multidevice)](https://github.com/aldinokemal/go-whatsapp-web-multidevice)** (`GOWA_BASE_URL`)

## Instalação

```bash
composer require gowa-php/laravel
```

O Service Provider e a Facade `Gowa` são registrados automaticamente através do Package Discovery do Laravel.

Publique o arquivo de configuração:

```bash
php artisan vendor:publish --tag=gowa-config
```

Publique e execute as migrações (migrations):

```bash
php artisan vendor:publish --tag=gowa-migrations
php artisan migrate
```

## Configuração

```env
GOWA_BASE_URL=https://gowa.suaempresa.com
GOWA_USERNAME=admin
GOWA_PASSWORD=secret
GOWA_TIMEOUT=15
GOWA_DEFAULT_DEVICE_ID=meu-dispositivo-padrao-uuid
GOWA_WEBHOOK_SECRET=sua_chave_hmac_secret
GOWA_WEBHOOK_PATH=webhooks/gowa
GOWA_WEBHOOK_AUTO_SYNC=true
GOWA_LOG_WEBHOOKS=false
```

> **`GOWA_WEBHOOK_SECRET` é obrigatório para receber webhooks.** O servidor GOWA assina
> todas as entregas com `X-Hub-Signature-256`, usando o secret do próprio device quando
> este o tem e o `WHATSAPP_WEBHOOK_SECRET` global caso contrário. Este package espelha
> essa ordem: primeiro `gowa_instances.webhook_secret`, depois `gowa.webhook.secret`.
> Sem secret de nenhum dos lados a assinatura não pode ser verificada e o pedido é
> rejeitado com `403` — um webhook não assinado nunca é aceite.

## Como Usar

### Envio Fluente de Mensagens (Recomendado)

Envie mensagens com uma interface fluente e expressiva:

```php
use Gowa\Laravel\Facades\Gowa;

// Enviar texto simples
Gowa::to('5511999998888')->text('Olá do Laravel!')->send();

// Especificar a instância/dispositivo remetente (opcional; usa o padrão ou primeira conectada)
Gowa::from('device-id')->to('5511999998888')->text('Olá de uma instância específica!')->send();

// Responder / citar uma mensagem anterior
Gowa::to($phone)->replyTo($messageId)->text('Respondendo à sua mensagem...')->send();
```

#### Mídias e Anexos com Laravel Storage

Anexe mídias facilmente a partir de URLs, caminhos locais, streams ou **Discos do Laravel Storage (S3, MinIO, Public, Local)**:

```php
// Imagem (via URL externa ou caminho de arquivo local)
Gowa::to($phone)->image('https://exemplo.com/banner.png', 'Oferta imperdível!')->send();

// Documento direto do Laravel Storage Disk (ex: Amazon S3) via streaming de dados
Gowa::to($phone)
    ->disk('s3')
    ->document('invoices/2026/fatura_1092.pdf', filename: 'Fatura.pdf', caption: 'Sua fatura do mês')
    ->send();

// Você também pode passar o disco como parâmetro direto
Gowa::to($phone)->image('banners/promo.jpg', caption: 'Promoção de Verão', disk: 'public')->send();

// Vídeo e Áudio
Gowa::to($phone)->video('videos/demonstracao.mp4', 'Demonstração do Produto')->send();
Gowa::to($phone)->audio('podcasts/episodio1.mp3')->send();

// Mensagem de voz gravada / PTT (Push-To-Talk)
Gowa::to($phone)->voice('audios/recado.ogg')->send();

// Figurinha / Sticker (WebP)
Gowa::to($phone)->sticker('stickers/joinha.webp')->send();
```

#### Mensagens Ricas: Localização, Contatos, Enquetes, Links e Reações

```php
// Localização Geográfica (Latitude e Longitude)
Gowa::to($phone)->location(-23.55052, -46.633309)->send();

// Contato / VCard
Gowa::to($phone)->contact('João Silva', '5511988887777')->send();

// Enquete Interativa (Poll)
Gowa::to($phone)
    ->poll('Qual o melhor horário para a reunião?', ['Manhã (09h)', 'Tarde (14h)', 'Noite (18h)'], maxSelections: 1)
    ->send();

// Link com preview rico
Gowa::to($phone)->link('https://antigravity.google', 'Plataforma Antigravity AI')->send();

// Reação com Emoji em uma mensagem
Gowa::to($phone)->reaction($messageId, '🔥')->send();
```

#### Ações Diretas em Mensagens

```php
// Marcar mensagem como lida ou áudio como reproduzido
Gowa::to($phone)->markRead($messageId, withTyping: false);
Gowa::to($phone)->markPlayed($audioMessageId);

// Revogar (apagar para todos) ou favoritar (star)
Gowa::to($phone)->revoke($messageId);
Gowa::to($phone)->star($messageId);
```

### Channel de Notificação (Notification Channel)

Implemente `toGowa()` em sua notificação e `routeNotificationForGowa()` em sua entidade notificável (ex: model User). O `GowaMessage` suporta todos os métodos fluentes de mídias e discos do Storage:

```php
use Gowa\Laravel\Notifications\GowaChannel;
use Gowa\Laravel\Notifications\GowaMessage;
use Illuminate\Notifications\Notification;

class OrderInvoiceNotification extends Notification
{
    public function __construct(public Order $order) {}

    public function via(mixed $notifiable): array
    {
        return [GowaChannel::class];
    }

    public function toGowa(mixed $notifiable): GowaMessage
    {
        return GowaMessage::create()
            ->disk('s3')
            ->document("invoices/{$this->order->id}.pdf", filename: 'Fatura.pdf', caption: "Segue a sua fatura referente ao pedido #{$this->order->id}!");
    }
}

// No seu Model User:
public function routeNotificationForGowa(): string
{
    return $this->phone_number; // ex: '5511999998888'
}
```

### Eventos de Webhook e Sincronização Automática no Banco (Auto-Sync)

O pacote registra automaticamente uma rota POST em `{GOWA_WEBHOOK_PATH}/{deviceId}`. Ele valida a assinatura HMAC usando o `webhook_secret` armazenado no model `GowaInstance`, e dispara eventos tipados do Laravel.

#### Sincronização Automática com o Banco (`GOWA_WEBHOOK_AUTO_SYNC=true`)

Quando habilitado (padrão `true`), o pacote faz tudo automaticamente:
- Cria / atualiza a conversa (`GowaConversation`) com os dados do contato.
- Grava as mensagens recebidas em `GowaMessage` (direção `inbound`, status `delivered`).
- Atualiza recibos de entrega e leitura (`delivered_at`, `read_at`, status `read`) ao receber confirmações (`message.ack`).
- Grava mensagens enviadas quando você usa `Gowa::to()->send()`.
- Os Listeners implementam `ShouldQueue` — o processamento roda de forma assíncrona na sua fila configurada no Laravel (Redis, Database, SQS) ou de forma síncrona (`sync`).
- Todas as entregas aceites são gravadas em `gowa_webhook_calls` pelo controller *antes* de os eventos serem despachados, com o URL e os headers do pedido (menos `authorization`, `cookie` e `proxy-authorization`). Se um dos listeners de sync do package rebentar, essa linha passa a `processed = false` e a exceção fica guardada — a falha é visível na tabela e não só em `failed_jobs`.

#### Escutando Eventos Personalizados

Você também pode escutar os eventos tipados em sua aplicação:

```php
use Gowa\Laravel\Webhook\Events\GowaMessageReceived;
use Gowa\Laravel\Webhook\Events\GowaMessageAck;
use Gowa\Laravel\Webhook\Events\GowaWebhookReceived;

// Qualquer webhook recebido (antes dos eventos específicos por tipo)
Event::listen(GowaWebhookReceived::class, function (GowaWebhookReceived $event) {
    // $event->deviceId é sempre o device a que a entrega foi dirigida.
    // $event->instanceId é null quando esse device não tem linha em gowa_instances.
    Log::info('GOWA webhook', [
        'event'    => $event->event->value,
        'device'   => $event->deviceId,
        'instance' => $event->instanceId,
    ]);
});

// Mensagem recebida
Event::listen(GowaMessageReceived::class, function (GowaMessageReceived $event) {
    $message = $event->message; // Gowa\Sdk\Webhook\Dto\IncomingMessage
    // processar lógica de negócio, chatbot ou agente de IA...
});

// Confirmação de entrega/leitura de mensagem
Event::listen(GowaMessageAck::class, function (GowaMessageAck $event) {
    $ack = $event->ack; // Gowa\Sdk\Webhook\Dto\IncomingAck
});

// Dica: Se o seu listener capturar uma exceção e desejar marcar a auditoria do webhook como falha:
use Gowa\Laravel\Models\GowaWebhookCall;

try {
    // processamento customizado...
} catch (\Throwable $e) {
    GowaWebhookCall::markFailed($event->webhookCallId, $e);
    throw $e;
}
```

### Models Eloquent

```php
use Gowa\Laravel\Models\GowaInstance;

// Buscar instância e verificar se está conectada
$instance = GowaInstance::where('device_id', 'meu-dispositivo')->firstOrFail();
$instance->status->isConnected(); // bool

// Criar um GowaClient direcionado para esta instância
$client = $instance->client();
$client->sendText('5511999998888', 'Olá!');

// Acessar conversas e mensagens
$instance->conversations()->with('messages')->get();
```

### Personalizando os Models

Aponte as configurações para suas próprias classes de Model (útil para adicionar relacionamentos ou colunas personalizadas):

```php
// config/gowa.php
'models' => [
    'instance'     => App\Models\WhatsappInstance::class,
    'conversation' => App\Models\WhatsappConversation::class,
    'message'      => App\Models\WhatsappMessage::class,
    'webhook_call' => App\Models\WhatsappWebhookCall::class,
],
```

### Suporte a Multi-Tenant (Teams)

Habilite o escopo para múltiplos times/tenants adicionando a coluna `team_id` nas migrações:

```env
GOWA_TEAMS=true
GOWA_TEAM_FOREIGN_KEY=team_id
```

Publique e re-execute as migrações após ativar essa configuração.

## Atualizando para a versão v1.1.0

### Breaking Changes e Passos de Migração

1. **`GOWA_WEBHOOK_SECRET` agora é obrigatório**: Todas as entregas de webhook devem ser assinadas via HMAC-SHA256. Se o dispositivo não possuir um `webhook_secret` próprio cadastrado na instância, o segredo global `gowa.webhook.secret` será utilizado. Requisições sem assinatura válida retornarão `403 Forbidden` (ou `404` se o dispositivo não estiver registrado e não houver segredo global).
2. **Publicar a Migration da Tabela de Auditoria**: Uma nova migration (`000004_create_gowa_webhook_calls_table.php`) registra as entregas de webhooks e estados de falha. Execute:
   ```bash
   php artisan vendor:publish --tag=gowa-migrations
   php artisan migrate
   ```
3. **Assinatura dos Construtores de Eventos de Webhook**: Os construtores de eventos tipados (`GowaWebhookReceived`, `GowaMessageReceived`, `GowaMessageAck`, `GowaMessageReaction`) agora recebem `?int $instanceId` e `string $deviceId`. Caso sua aplicação instancie esses eventos manualmente em testes, atualize as chamadas fornecendo o `$deviceId`.
4. **Atualização do Arquivo de Configuração**: Se estiver atualizando da v1.0, republique a configuração:
   ```bash
   php artisan vendor:publish --tag=gowa-config --force
   ```

## Executando os Testes

Por padrão, os testes são executados utilizando SQLite em memória sem a necessidade de contêineres ou serviços externos:

```bash
composer test
# ou explicitamente:
composer test:sqlite
```

Para executar a suíte de testes no MySQL e PostgreSQL utilizando o Docker:

```bash
# Subir os contêineres do MySQL e PostgreSQL
docker compose up -d

# Executar os testes para cada banco de dados
composer test:mysql
composer test:pgsql
```

## ⚠️ Isenção de Responsabilidade e Termos de Uso (Disclaimer)

Este software é uma biblioteca open-source desenvolvida para fins **educacionais, de pesquisa e laboratório de testes**.

- **Termos de Serviço de Terceiros**: Os usuários desta biblioteca são inteiramente responsáveis pelo cumprimento dos Termos de Serviço do WhatsApp, das Políticas da Plataforma Meta e dos termos de uso de quaisquer serviços de terceiros utilizados.
- **Envio Automatizado e Privacidade**: O envio automatizado ou não autorizado de mensagens pode violar os termos das plataformas. Cabe aos usuários garantir conformidade estrita com as leis de privacidade aplicáveis (ex: LGPD, GDPR), consentimento prévio dos destinatários e diretrizes das ferramentas.
- **Ausência de Garantias e Responsabilidade**: Este software é fornecido "como está" (*as is*), sem garantias de qualquer tipo, expressas ou implícitas. Os autores e contribuidores não se responsabilizam por eventuais bloqueios de números, banimentos de contas, perda de dados ou mau uso desta biblioteca.

## Licença

Este pacote é um software open-source licenciado sob a [Licença MIT](LICENSE).
