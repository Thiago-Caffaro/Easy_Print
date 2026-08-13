# Contrato de integração CUPS

Revisado em 2026-08-13. CUPS/driver instalado é a autoridade final.

## Destino conhecido

- Fila: `EPSON_L4150_Series`
- CUPS: acessível internamente por IPP, porta 631
- Exemplo observado pelo usuário: `http://100.119.152.43:631/printers/EPSON_L4150_Series`

Não fixar o IP no código. Usar `CUPS_SERVER`, `CUPS_PORT` e `CUPS_PRINTER` validados.

## Operações iniciais

```text
lpoptions -h HOST:PORT -p PRINTER -l
lpoptions -h HOST:PORT -p PRINTER
lpstat -h HOST:PORT -p PRINTER -l
lpstat -h HOST:PORT -o PRINTER
lp -h HOST:PORT -d PRINTER [opções] -- ARQUIVO
cancel -h HOST:PORT JOB_ID
```

Confirmar a sintaxe disponível na versão instalada. Não executar comandos administrativos no fluxo normal.

## Capacidade para UI

Normalizar cada opção como:

```text
key, label, values[{value,label}], default, category, source
```

Categorias úteis: papel, mídia, cor, qualidade, orientação, lados, layout e acabamento. Nomes possíveis incluem `PageSize`, `media`, `MediaType`, `InputSlot`, `ColorModel`, `print-color-mode`, `Resolution`, `print-quality`, `orientation-requested`, `sides`, `number-up`, `fit-to-page` e `print-scaling`. A presença do nome não prova que todos os valores genéricos funcionam: usar somente valores retornados pelo destino.

## Opções genéricas do job

- Cópias: `-n` com inteiro em faixa definida pela aplicação.
- Páginas: `-P` com gramática estrita de números e intervalos; páginas são de saída e podem ser afetadas por `number-up`.
- Título: `-t` com texto curto sanitizado; não usar caminho interno.
- Opções: pares `-o`, `nome=valor`, exclusivamente após validação dinâmica.

## Uploads

- MVP: PDF.
- Validar erro de upload, limite, extensão, MIME via `finfo` e assinatura/estrutura mínima.
- Mover com nome aleatório para diretório fora do webroot.
- Não usar o nome original como caminho nem argumento de título sem sanitização.
- Remover temporário após aceitação pelo spool, salvo retenção explícita.

## Evolução

Começar pelos utilitários CUPS. Avaliar IPP direto apenas se atributos estruturados eliminarem parsing frágil ou trouxerem estado necessário. PPD e drivers tradicionais estão em processo de descontinuação no CUPS; manter o domínio independente do formato de descoberta.

## Fontes oficiais

- `lp`: https://openprinting.github.io/cups/doc/man-lp.html
- `lpoptions`: https://openprinting.github.io/cups/doc/man-lpoptions.html
- Opções de impressão: https://openprinting.github.io/cups/doc/options.html
- Visão geral do CUPS: https://openprinting.github.io/cups/
- `proc_open` com comando em array: https://www.php.net/manual/en/function.proc-open.php
