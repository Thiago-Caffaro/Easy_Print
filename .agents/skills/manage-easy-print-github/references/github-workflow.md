# Modelo GitHub do Easy Print

Revisado em 2026-08-13.

## Project mínimo

Campos recomendados:

- Status: `Backlog`, `Ready`, `In progress`, `In review`, `Done`.
- Priority: `P0`, `P1`, `P2`, `P3`.
- Area: `Web`, `CUPS`, `Jobs`, `Uploads`, `Deploy`, `Docs`, `Scanner`.
- Size: `XS`, `S`, `M`, `L`; dividir itens `L` antes de iniciar.
- Target date apenas quando houver compromisso real.
- Iteration somente se o projeto adotar ciclos; não criar sprints artificiais para trabalho contínuo.

Visões úteis:

- Backlog por prioridade.
- Board por status com limite de trabalho em progresso.
- Roadmap apenas para iniciativas com datas ou sequência significativa.

Automatizar: adicionar Issues relevantes, definir `Backlog` ao entrar, mover para `Done` ao fechar e arquivar itens concluídos após período definido.

## Labels

Usar labels para natureza transversal, não para repetir campos do Project:

- Tipo: `bug`, `feature`, `chore`, `spike`.
- Risco/contexto: `security`, `breaking-change`, `needs-printer`.

Evitar labels de status e prioridade quando esses dados já forem campos do Project.

## Estrutura de Issue

```markdown
## Resultado
Comportamento observável que deve existir.

## Contexto
Motivação, evidências e limites relevantes.

## Critérios de aceitação
- [ ] Resultado verificável 1
- [ ] Resultado verificável 2

## Fora de escopo
O que esta Issue deliberadamente não resolve.

## Verificação
Testes automatizados e validação manual necessária.

## Dependências e riscos
Links para bloqueadores, decisões e necessidade de CUPS/impressora real.
```

## Wiki inicial

- Home: objetivo, estado atual e navegação.
- Architecture: limites entre navegador, app, CUPS e impressora.
- Printing capabilities: capacidades observadas e como atualizá-las.
- Operations: deploy, backup, logs, healthcheck e rollback.
- Troubleshooting: CUPS indisponível, fila pausada, job travado e upload rejeitado.
- Security: modelo de ameaça interno, acesso Tailscale e retenção.
- Scanner feasibility: somente quando a investigação começar.

## Marcos sugeridos

- `MVP Printing`: descoberta, PDF, opções básicas, envio, fila e cancelamento.
- `Operational hardening`: deploy, observabilidade, autenticação opcional e retenção.
- `Image support`: PNG/JPEG após testes.
- `Scanner feasibility`: investigação separada, sem promessa de entrega.

## Fontes oficiais

- Planejamento com Issues e Projects: https://docs.github.com/en/issues/tracking-your-work-with-issues/learning-about-issues/planning-and-tracking-work-for-your-team-or-project
- Boas práticas de Projects: https://docs.github.com/en/issues/planning-and-tracking-with-projects/learning-about-projects/best-practices-for-projects
- Wiki: https://docs.github.com/en/communities/documenting-your-project-with-wikis
