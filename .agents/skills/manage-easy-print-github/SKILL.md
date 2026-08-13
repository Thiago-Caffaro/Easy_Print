---
name: manage-easy-print-github
description: Planejar e manter o Easy Print no GitHub usando Issues, sub-issues, Projects, labels, milestones, pull requests e Wiki. Usar ao transformar requisitos em backlog, criar critérios de aceitação, organizar prioridades e dependências, atualizar roadmap/status ou produzir documentação longa e runbooks na Wiki. Não usar para guardar configuração executável ou documentação que precise mudar atomicamente com o código; manter esses itens no repositório.
---

# Gerenciar Easy Print no GitHub

Usar GitHub como sistema de colaboração sem duplicar a mesma informação em Issues, Project, Wiki e arquivos do repositório.

## Ler o modelo de trabalho

Ler [references/github-workflow.md](references/github-workflow.md) antes de criar estrutura, campos, labels ou páginas.

## Escolher a fonte de verdade

- Issue: trabalho acionável, contexto, critérios de aceitação, dependências e discussão específica.
- Project: estado, prioridade, tamanho, área, datas e diferentes visões do mesmo backlog.
- Pull request: mudança proposta, evidência de verificação e vínculo com a Issue.
- Wiki: arquitetura explicativa, guias de uso e runbooks operacionais duráveis.
- Repositório: código, README de entrada, configuração de exemplo, contratos, migrações e documentação que deve acompanhar uma versão do código.

## Planejar trabalho

1. Inspecionar Issues, Project, Wiki e código existentes antes de criar algo.
2. Definir um resultado observável e separar descoberta técnica de implementação quando houver incerteza real.
3. Criar Issues pequenas o bastante para produzir PRs revisáveis; usar uma Issue-pai e sub-issues para iniciativas maiores.
4. Escrever critérios de aceitação verificáveis, riscos e dependências. Não transformar detalhes triviais de implementação em burocracia.
5. Vincular Issue, PR e página da Wiki relevante.
6. Atualizar o Project por campos e automações; não duplicar status em labels.

## Operar com ferramentas

- Quando houver conector GitHub disponível, ler o estado remoto antes de qualquer mutação e limitar alterações ao repositório/projeto solicitado.
- Criar ou editar itens externos apenas quando o usuário pedir execução, não apenas uma proposta ou revisão.
- Se o conector não estiver disponível, produzir rascunhos completos e indicar claramente que nada foi criado no GitHub.
- Nunca inventar números de Issue, URLs, campos ou estados remotos.

## Atualizar documentação

- Alterar a Wiki quando comportamento, arquitetura ou operação durável mudar.
- Manter páginas curtas, ligadas por uma Home e uma Sidebar, com proprietário lógico e data de revisão quando útil.
- Registrar no repositório decisões que precisam ser revisadas junto com o código; a Wiki pode apontar para elas.
- Evitar copiar README para a Wiki. O README deve permitir entender e iniciar o projeto; a Wiki aprofunda.

## Encerrar uma atualização

- Conferir links, responsáveis, critérios de aceitação e relações de bloqueio.
- Confirmar que existe uma única fonte para status, prioridade e data-alvo.
- Resumir o que foi criado ou alterado e listar qualquer ação manual restante.
