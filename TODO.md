# TODO — gowa-laravel

Levantamento feito em 2026-09-01 sobre o trabalho não commitado da sessão anterior
(API fluente + auto-sync de webhooks + tabela de auditoria).

## P2 — Correção / integridade de dados

- [x] **Bug do Ano 8400 no parse de timestamp numérico** — `src/Webhook/Listeners/SyncIncomingMessage.php:76`
      Timestamps Unix numéricos (ex: `"1725368400"`) tratados com `Carbon::createFromTimestamp()`.
- [x] **Sobrescrita destrutiva de `contact_name`** — `src/Webhook/Listeners/SyncIncomingMessage.php:50`
      `contact_name` não é mais sobrescrito por `null` nem por display names de mensagens de eco.
- [x] **Mensagens de eco do próprio aparelho (`isEcho`)** — `src/Webhook/Listeners/SyncIncomingMessage.php:70`
      Gravadas como `Outbound` e `Sent` em vez de `Inbound` e `Delivered`.
- [x] **Anti-regressão de status em acks concorrentes** — `src/Webhook/Listeners/SyncMessageAck.php:45`
      Status `Read` protegido contra rebaixamento tardio para `Delivered`.
- [x] **Suporte a multi-tenancy `teams` e índice de pruning** — `database/migrations/2025_01_01_000004_create_gowa_webhook_calls_table.php`
      Chave estrangeira de time tornada `nullable()` e índice adicionado em `created_at` para `MassPrunable`.

## P3 — Design

- [x] **Sync de outbound preso a config de webhook** — `src/PendingMessage.php` & `src/Concerns/BuildsMessagePayload.php`
      Desacoplado via `gowa.auto_sync.outbound` e `gowa.auto_sync.inbound`. `recordOutboundMessage()` movido para
      a trait `BuildsMessagePayload`, permitindo que `GowaChannel` também registre envios automaticamente.

- [x] **Falha de listener de utilizador continua sem rasto**
      Documentada a utilização de `GowaWebhookCall::markFailed($event->webhookCallId, $e)` nos READMEs.

- [x] **`Gowa::$runsMigrations` é estado estático global** — `src/Facades/Gowa.php:47`
      Reset automático garantido em `TestCase::tearDown()`.

## P4 — Entrega

- [x] **Escrever notas de upgrade.** Seção de Upgrade Guide para v1.1.0 documentada em `README.md` e `README.pt.md`.

- [x] **Commitar o trabalho.** Fatiado em commits convencionais semânticos.

- [x] **`.ai-memory.toml`** Mantido e versionado espelhando o padrão de `gowa-php/sdk`.

## Feito

- [x] **Ciclo de vida do registo de auditoria (opção A)**
      A linha em `gowa_webhook_calls` passa a ser escrita pelo controller
      (`recordCall()`), sincronamente, antes de despachar — com `url`, `headers`
      filtrados e `processed = true`. Os eventos levam `?int $webhookCallId`, e
      `SyncIncomingMessage` / `SyncMessageAck` apanham `Throwable`, chamam
      `GowaWebhookCall::markFailed()` (grava `processed = false` + `exception`) e
      voltam a lançar. `LogWebhookRequest` ficou só com o log.
      Teste de integração com model que rebenta prova o caminho de falha.
      `68 passed (153 assertions)`.

- [x] **`deviceId` real na auditoria + fim da sentinela `instanceId = 0`**
      Os quatro eventos passam a `?int $instanceId` + `string $deviceId`;
      `GowaWebhookReceived` ganha também `url` e `headers`. Controller envia null em vez
      de `0` para device sem linha em DB e passa `fullUrl()` + headers filtrados
      (`authorization`, `cookie`, `proxy-authorization` fora). `LogWebhookRequest` grava
      o `deviceId` verdadeiro, `url` e `headers`; `SyncMessageAck` sai cedo com
      `instanceId` null. 3 testes novos. READMEs atualizados.
      **Breaking:** assinatura dos eventos mudou — quem construa os eventos à mão
      tem de acrescentar `$deviceId`. `67 passed (150 assertions)`.

- [x] **`verifyWebhookSignature` aceitava tudo sem secret de device** — `src/Models/GowaInstance.php`
      `if (empty($this->webhook_secret)) return true;` aceitava qualquer POST para
      instância registada sem secret próprio — o caso default. Passa a espelhar a
      resolução do servidor: secret do device, senão `gowa.webhook.secret`; sem
      nenhum, 403. Confirmado contra `go-whatsapp-web-multidevice`: o servidor assina
      sempre (`webhook.go:65`) e cai para o secret global (`webhook.go:22,28-30`),
      que nunca é vazio (`settings.go:49`). 10 testes passaram a enviar pedidos
      assinados via helper `postWebhook()` em `tests/Pest.php`.
      READMEs documentam que `GOWA_WEBHOOK_SECRET` é obrigatório.
      `64 passed (138 assertions)`.

- [x] **Bypass de assinatura via `default_device_id`** — `src/Webhook/GowaWebhookController.php`
      O ramo `elseif` que aceitava webhook não assinado quando o `deviceId` batia com
      `gowa.default_device_id` foi removido. Device desconhecido passa a exigir sempre
      `gowa.webhook.secret` + HMAC válido; sem secret devolve 404, com assinatura errada 403.
      Regressão coberta por 2 testes novos em `tests/Feature/GowaWebhookControllerTest.php`.
      `60 passed (132 assertions)`.

- [x] **Null-deref nos dispatches do controller** — `GowaWebhookController.php:62,70,73,76`
      Usavam `$instance->id` com `$instance` null no ramo stateless (500).
      Passaram a usar `$instanceId`. `58 passed (127 assertions)`.
