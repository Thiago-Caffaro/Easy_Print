---
name: develop-easy-print
description: Implementar, refatorar, testar ou revisar funcionalidades do Easy Print em PHP 8.x e Slim 4. Usar ao estruturar o projeto, criar rotas, ações, serviços, templates server-rendered, componentes HTMX, persistência SQLite, Docker, testes ou documentação técnica. Não usar como fonte principal para detalhes de comandos e capacidades CUPS; combinar com integrate-cups-safely nesses casos.
---

# Desenvolver Easy Print

Construir o Easy Print como uma camada web pequena sobre o CUPS, preservando simplicidade, testabilidade e segurança.

## Começar pelo contexto

1. Ler o `AGENTS.md` aplicável e inspecionar o estado real do repositório.
2. Ler [references/project-architecture.md](references/project-architecture.md) antes de criar o esqueleto, mudar limites arquiteturais ou adicionar dependências.
3. Identificar o pedido ou Issue, seus critérios de aceitação e a menor fatia vertical verificável.
4. Para qualquer código que consulte, interprete ou invoque CUPS, usar também `$integrate-cups-safely`.

## Implementar uma fatia vertical

Manter o caminho principal explícito:

`rota -> action -> serviço de aplicação -> adaptador externo/repositório -> resposta/template`

- Manter Actions finas: extrair entrada validada, chamar um caso de uso e montar a resposta.
- Concentrar regras de impressão, uploads e jobs em serviços testáveis sem HTTP.
- Isolar processos CUPS atrás de uma interface e de um executor de processos.
- Introduzir interfaces em fronteiras externas ou quando houver duas implementações reais; não criar abstrações cerimoniais.
- Renderizar HTML no servidor. Usar HTMX para fragmentos e polling; usar JavaScript próprio apenas quando HTML/HTMX não resolverem bem.
- Adotar SQLite apenas quando histórico, preferências ou retenção exigirem estado próprio.
- Fazer mudanças de esquema por migrações versionadas e transações.

## Dependências

- Preferir componentes PSR pequenos e mantidos.
- Justificar cada dependência de produção pela capacidade concreta que evita reimplementar.
- Fixar versões por Composer e versionar `composer.lock` para a aplicação.
- Não introduzir Laravel, Symfony completo, React, filas distribuídas ou serviços separados sem requisito comprovado.

## Testar em camadas

- Testar unidades puras para validadores, normalizadores, parsers e regras de estado.
- Testar integração HTTP para upload, CSRF, respostas e erros.
- Testar integração CUPS com fixtures determinísticas primeiro; reservar testes contra CUPS real para ambiente explicitamente configurado.
- Cobrir caminho feliz, entrada inválida, timeout, comando com saída inesperada e serviço indisponível.
- Rodar somente comandos existentes e documentados no repositório. Nunca afirmar que um teste passou se não foi executado.

## Concluir

- Verificar formatação, análise estática e testes configurados.
- Confirmar que logs não incluem conteúdo de documentos, segredos ou caminhos desnecessários.
- Atualizar README, configuração de exemplo e Wiki/Issue apenas quando a mudança alterar uso, operação ou planejamento.
- Resumir resultado, decisões relevantes, verificações executadas e riscos restantes.
