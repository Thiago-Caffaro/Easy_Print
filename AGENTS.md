# Easy Print — instruções do repositório

## Produto

- Construir uma interface web interna para a fila CUPS `EPSON_L4150_Series`.
- Tratar o CUPS como fonte de verdade para impressora, capacidades, fila e jobs.
- Manter a aplicação pequena: PHP 8.x, Slim 4, HTML/CSS renderizado no servidor, HTMX e JavaScript mínimo.
- Não presumir recursos da impressora. Detectar opções reais via CUPS/IPP/PPD e adaptar a interface.
- Manter scanner fora do MVP de impressão e como módulo opcional separado.

## Forma de trabalhar

- Implementar a menor fatia vertical que satisfaça critérios de aceitação observáveis.
- Preferir código explícito e composição simples a abstrações antecipadas.
- Não adicionar framework, serviço, banco ou dependência de produção sem necessidade demonstrável.
- Preservar separação entre HTTP, domínio da aplicação, integração CUPS e persistência.
- Atualizar testes e documentação afetados na mesma mudança.
- Executar os comandos de validação definidos pelo projeto antes de concluir. Se ainda não existirem, registrar essa lacuna sem inventar sucesso.

## Segurança obrigatória

- Nunca concatenar entrada do usuário em comandos de shell.
- Executar processos com executável e argumentos separados, timeout e captura de stdout, stderr e código de saída.
- Validar impressora, opções CUPS e valores por allowlists derivadas das capacidades detectadas.
- Validar uploads por tamanho, extensão e conteúdo/MIME no servidor; usar nomes aleatórios e armazenamento fora do webroot.
- Exigir CSRF em operações mutáveis e não expor detalhes internos em erros enviados ao navegador.

## Documentação e planejamento

- Usar Issues para trabalho acionável e critérios de aceitação.
- Usar GitHub Projects para estado, prioridade, visão de backlog e roadmap; não duplicar esses dados em documentos.
- Usar a Wiki para arquitetura explicativa, decisões operacionais e runbooks duráveis.
- Manter no repositório tudo que precisa mudar junto com o código: README, configuração de exemplo, contratos, migrações e instruções de teste.

## Code Review Rules

- Sinalizar como bloqueante qualquer caminho que permita injeção de comando, opção CUPS arbitrária ou acesso a arquivo fora do diretório permitido.
- Sinalizar capacidades de impressora hardcodadas sem evidência em fixture ou descoberta real.
- Exigir teste para parsers de saída do CUPS, validação de opções e transições de jobs.
- Evitar aprovar complexidade estrutural sem um caso de uso atual que a justifique.
