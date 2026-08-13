# Arquitetura de referência do Easy Print

Revisado em 2026-08-13. Confirmar versões suportadas antes de adicionar dependências.

## Restrições do produto

- Uso interno por LAN/Tailscale; não é SaaS.
- CUPS existente no TrueNAS SCALE, fila `EPSON_L4150_Series`, IPP na porta 631.
- Aplicação e CUPS em containers separados. A aplicação não recebe acesso USB nem ao spool inteiro.
- PDF é o formato inicial. PNG/JPEG entram somente depois de testes reais.
- Scanner é módulo independente e posterior, sujeito a viabilidade via SANE/airscan.

## Stack alvo

- PHP 8.x suportado pelo conjunto de dependências escolhido.
- Slim Framework 4 e implementação PSR-7.
- Templates PHP, CSS leve e HTMX.
- PSR-3 para logs; IDs de correlação por requisição.
- PHPUnit compatível com a versão de PHP do projeto.
- SQLite/PDO somente se houver estado próprio.
- Cliente CUPS no container da aplicação para `lp`, `lpstat`, `lpoptions` e `cancel`.

O Slim 4 não inclui container próprio. Usar PHP-DI apenas se a composição manual deixar de ser clara; um bootstrap explícito é suficiente no início.

## Estrutura inicial

```text
app/
  Application/       casos de uso e DTOs
  Domain/            regras e tipos sem dependência HTTP/CUPS
  Infrastructure/    CUPS, SQLite, filesystem e relógio
  Http/              actions e middleware
  Views/             páginas e fragmentos
config/
public/
storage/
tests/
  Unit/
  Integration/
```

Evitar pastas vazias e camadas sem comportamento. Criar cada diretório quando surgir o primeiro caso real.

## Configuração

- Ler configuração operacional de variáveis de ambiente e validá-la no startup.
- Fornecer `.env.example` sem segredos.
- Separar configuração de desenvolvimento, teste e produção.
- Falhar cedo para impressora, host CUPS, diretórios ou limites inválidos.

## Fronteiras

- HTTP não conhece sintaxe de comando CUPS.
- O executor de processos não conhece regras de impressão.
- O adaptador CUPS converte resultados externos em tipos internos estruturados.
- Templates recebem view models e não consultam serviços.
- Histórico local complementa a fila; nunca substitui o estado operacional do CUPS.

## Fontes oficiais

- Slim 4, middleware e erros: https://www.slimframework.com/docs/v4/
- PHP, uploads e processos: https://www.php.net/manual/en/features.file-upload.php e https://www.php.net/manual/en/function.proc-open.php
- PHPUnit: https://docs.phpunit.de/
