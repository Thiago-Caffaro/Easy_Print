---
name: integrate-cups-safely
description: Implementar, diagnosticar, testar ou revisar a integração do Easy Print com CUPS/IPP, incluindo descoberta de capacidades, opções da Epson L4150, status da impressora, envio e cancelamento de jobs, parsing de lpoptions/lpstat e execução segura de lp/cancel. Usar sempre que uma mudança tocar comandos CUPS, fila de impressão, uploads enviados à impressora ou capacidades dinâmicas. Não usar para administração geral do servidor CUPS nem presumir recursos do hardware.
---

# Integrar CUPS com segurança

Tratar CUPS como sistema externo e fonte de verdade. Derivar a UI de capacidades observadas, nunca do nome do modelo da impressora.

## Ler o contrato

Ler [references/cups-contract.md](references/cups-contract.md) antes de alterar descoberta, parsing, argumentos, estados ou mensagens de erro.

## Fluxo obrigatório

1. Confirmar o destino configurado e a conectividade sem alterar o servidor.
2. Capturar defaults, capacidades e status reais.
3. Preservar saídas reais sanitizadas como fixtures de teste.
4. Converter a saída externa para DTOs estruturados; não espalhar parsing por Actions ou templates.
5. Construir a lista de valores aceitos a partir do snapshot de capacidades.
6. Validar cada opção recebida contra essa lista antes de montar argumentos.
7. Enviar o job, capturar o ID retornado e reconciliar o estado posterior com CUPS.

## Executar processos

- Usar `proc_open()` com comando em array: executável e cada argumento em um elemento separado.
- Definir executáveis permitidos por configuração ou constantes internas; nunca aceitar o nome do comando da requisição.
- Passar `--` antes do caminho do arquivo quando o utilitário aceitar.
- Aplicar timeout, limitar tamanho de stdout/stderr e sempre capturar código de saída.
- Definir ambiente mínimo e previsível, incluindo locale estável para parsers quando necessário.
- Redigir mensagens externas; registrar diagnóstico com request ID e job ID, sem conteúdo do documento.
- Não usar `shell_exec`, `exec` ou `proc_open` com string concatenada.

## Modelar capacidades

- Manter o valor técnico original separado do rótulo traduzido.
- Marcar default explicitamente.
- Ocultar opção desconhecida até existir mapeamento e teste, mas preservar a descoberta para diagnóstico.
- Mostrar duplex, resolução, origem, mídia sem bordas ou qualidade apenas quando suportados no snapshot atual.
- Invalidar/cachear capacidades por tempo curto e oferecer atualização; lidar com mudança de driver sem quebrar formulários abertos.

## Tratar jobs

- Validar IDs com formato estrito e conferir que pertencem à impressora permitida.
- Expor cancelamento somente para estado cancelável.
- Considerar o retorno do comando e a consulta posterior; não marcar como cancelado apenas pelo clique da UI.
- Tratar estado desconhecido como desconhecido, sem convertê-lo silenciosamente em sucesso ou falha.

## Verificar

- Testar parsers contra múltiplas fixtures, incluindo locale/whitespace inesperados e saída incompleta.
- Testar geração de argumentos sem executar o processo.
- Testar rejeição de impressora, opção, valor, páginas, cópias e job ID inválidos.
- Manter testes reais de CUPS opt-in e sem imprimir por padrão; qualquer teste que gere papel precisa de autorização explícita.
