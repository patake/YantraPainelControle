Yantra Agente · Plano de desenvolvimento

# Plano do Agente Yantra

O app de bandeja atual (antes Yantra PDF, agora Yantra Agente) vai virar o agente instalado em cada equipamento. O agente conversa com o painel administrativo (Laravel, na nuvem), gera PDFs, captura telas e lê dados da porta serial. A mesma equipe desenvolve o agente e o administrativo.

## Cronograma

As datas usam dias úteis e descontam os feriados nacionais. Em cada etapa, a parte do administrativo vem antes da parte do agente, porque o agente usa os endpoints novos. A captura de tela e a porta serial chegam aos equipamentos pela atualização automática.

Administrativo (API e painel Laravel) Agente (serviço e app de bandeja) Concluído (checklist) Versão publicada

## Já entregue

Estas entregas aconteceram antes da E0. Elas formam a base do agente.

- **v1.0.0 (24/10/2026):** impressora virtual Yantra Printer, serviço pdfprinterd, app de bandeja e instalador único. Testado no Windows: a impressora gera o PDF e o app mostra a notificação.
- **Nome Yantra Agente (25/10/2026):** o app, o instalador e a documentação usam o nome novo. A atualização da v1.0.0 para o Yantra Agente foi testada e funcionou.
- **Pasta nova (25/10/2026):** o projeto do agente mudou para C:\\YantraAgente.

## Arquitetura alvo

O serviço roda como SYSTEM e tem a responsabilidade pelas partes que o usuário não pode alterar: assinatura do equipamento, licença, comunicação com a API, acesso ao ERP do hospital, leitura da porta serial, cópia para o file server e instalação das atualizações. O ERP fica na rede do hospital, onde a nuvem não chega. Por isso o agente executa os SQLs do ERP. O app de bandeja roda na sessão de cada usuário do Windows. O app mostra as notificações, os assistentes e a tela Sobre, e captura a tela.

```
Administrativo (Laravel, AWS São Paulo · sa-east-1)
 ├─ área Staff Weega: clientes, usuários, sistemas, licenças, equipamentos, exames, logs e auditoria
 ├─ área Cliente: login, execução dos exames, interação com o agente
 └─ API do agente
   ▲  HTTPS · token por equipamento · respostas assinadas · consulta periódica de comandos
   │
serviço pdfprinterd (Go, SYSTEM, um por equipamento)   app de bandeja Yantra Agente (Flutter, um por sessão de usuário)
 ├─ assinatura do equipamento + registro                ├─ menu: Sobre, Forçar sincronização, Sair
 ├─ licença: trial / ativa / bloqueada / offline        ├─ estado da licença, notificações e tela Sobre
 ├─ parâmetros (cache para uso sem conexão)             ├─ captura de tela (combinação de teclas do assistente)
 ├─ ciclo de exame: pasta temporária, envio, cópia      ├─ assistentes: ativação (CNPJ), porta COM e teclas
 ├─ fila de envio offline                               └─ aviso e aprovação de atualização
 ├─ porta serial 9600 bps 8N1 + regras regex do painel
 ├─ ERP do hospital (Tasy/Oracle, rede local): consulta, autenticação e gravação do laudo
 ├─ impressora virtual Yantra Printer (porta 9100)
 └─ download e instalação silenciosa de atualizações
        ▲ API local 127.0.0.1:9187 (eventos SSE) ▼
```

### Decisões confirmadas

1. **Nome:** o projeto se chama **Yantra Agente**. A impressora continua com o nome **Yantra Printer**.
2. **Equipe:** 2 pessoas (o desenvolvedor e o Claude Code). A mesma equipe desenvolve o agente e o administrativo, em etapas intercaladas.
3. **Ordem das etapas:** a atualização automática (E3) vem antes das funções novas.
4. **Núcleo do agente:** o serviço do Windows é o núcleo. O mesmo instalador coloca o serviço e o app de bandeja em cada equipamento. Os dois trabalham juntos pela API local 127.0.0.1:9187.
5. **Instalação global:** o agente funciona para qualquer usuário do Windows. O instalador configura o equipamento inteiro, não só o usuário que instala.
6. **Instalação e ativação:** a instalação funciona sem internet. O registro e a ativação precisam de internet. Até o primeiro registro, o agente fica no estado "aguardando registro" e não captura exames, porque os parâmetros vêm da API.
7. **Trial:** o trial dura 7 dias a partir do registro. Depois do prazo, o agente bloqueia automaticamente. Só a Staff Weega desbloqueia, no administrativo.
8. **Porta serial:** a conexão é genérica: 9600 bps, 8 bits de dados, 1 bit de parada, sem paridade. O conteúdo muda conforme o equipamento. Regras regex definidas no painel extraem os dados. A leitura USB fica para uma etapa futura, fora deste cronograma.
9. **Combinação de teclas:** um assistente pede ao usuário a combinação da captura de tela. O assistente sugere Ctrl + Y e testa se a combinação está livre no equipamento.
10. **Certificado de assinatura de código:** a Weega Technologies compra o certificado.
11. **Um exame por vez:** o agente tem só um ciclo de exame aberto. O operador inicia o exame no painel, para um equipamento. Todas as ações de exame ficam no painel.
12. **Menu da bandeja:** o menu tem só três itens: Sobre, Forçar sincronização e Sair.
13. **File server:** os equipamentos quase sempre estão num domínio. O serviço copia os arquivos com a conta do computador. Fora de domínio, o serviço usa uma credencial de rede dos parâmetros.
14. **Pastas e nomes:** capturas em C:\\ProgramData\\YantraAgente\\capturas (os parâmetros podem mudar). A pasta final usa o padrão do Yantra Cardio 3.0: \<PASTA>\\\<PACIENTE>-\<NASCIMENTO>\\aaaa\\mm\\dd\\\<Exame>-\<ATENDIMENTO>-\<n>.pdf. Assim, os arquivos novos ficam junto com o histórico dos clientes atuais.
15. **Canal de comandos:** o agente consulta a API a cada poucos segundos. O WebSocket (Laravel Reverb) pode vir depois.
16. **Assinatura do equipamento:** a chave é o hash do UUID da placa-mãe (SMBIOS) e do MachineGuid do Windows. Nome, tipo, domínio e MACs vão para o painel só como informação.
17. **Tela Sobre:** o app tem uma tela Sobre com a versão, os dados da licença, os sistemas licenciados e o contato da Weega Technologies.
18. **Início automático:** o agente inicia com o Windows em todas as sessões. O menu da bandeja não tem mais o item "Iniciar com o Windows".
19. **Sistemas:** a licença é por cliente e por sistema. Cada sistema libera uma função do agente: YC PRT e YO PRT (impressora), YO SRC (captura de tela) e YO HUB (porta serial).
20. **Hospedagem:** AWS, região de São Paulo (sa-east-1), por causa da LGPD e da latência. Os arquivos ficam num bucket S3 privado com duas pastas: previews/ (cópias de preview, apagadas no fim do exame e, como segunda garantia, depois de 1 dia) e agent-releases/ (instaladores do agente). Em desenvolvimento, o LocalStack faz o papel do S3. (decidido em 25/10/2026)
21. **Cadastro antes da instalação:** a Staff Weega cadastra a empresa e o pacote de licenças de cada sistema antes de o cliente instalar o agente.
22. **Ativação por CNPJ:** na primeira execução, o assistente de ativação pede o CNPJ da empresa. O assistente mostra os sistemas e as licenças disponíveis. Se o CNPJ existe no cadastro e há licença disponível, o agente gera a assinatura, registra o equipamento e ativa o trial.
23. **Identidade visual:** YC PRT (Yantra Cardio Printer) e YO PRT (Yantra Optical Printer) são a mesma aplicação, para cardiologia e para oftalmologia. Só mudam o nome, os ícones e as cores. Existe um instalador só. Depois da ativação, o nome fica "Yantra Agente" mais o nome do sistema ativado.
24. **Papéis:** Staff Weega tem administrador e suporte. Cliente tem operador.
25. **Liberação de cada instalação:** a Staff Weega define a quantidade máxima de licenças do CNPJ. Cada instalação nova entra em trial e aguarda a decisão da Staff Weega. Só a Staff Weega ativa e desativa licenças.
26. **Um sistema por computador:** no assistente, o técnico marca um sistema entre os sistemas disponíveis para o CNPJ.
27. **Sem visualizador no agente:** o agente não abre janela de PDF. A conferência acontece só no painel. O agente mostra uma notificação quando o arquivo está disponível no painel.
28. **Tecnologia do administrativo:** repositório separado, PHP 8.5.12 ou mais recente, a versão mais recente do Laravel e PostgreSQL. O ambiente de desenvolvimento roda no Docker.
29. **Pastas e repositórios:** o agente fica em C:\\YantraAgente (GitHub patake/YantraAgente). O administrativo fica em C:\\YantraPainelControle (GitHub patake/YantraPainelControle).
30. **Integração com o ERP:** o agente executa os SQLs do exame no Oracle do Tasy, na rede do hospital. O painel guarda os SQLs e manda comandos. O agente devolve o resultado ao painel.
31. **Lista de pacientes:** o agente executa o SQL de consulta do exame e envia as linhas ao painel. O operador escolhe o paciente no painel.
32. **Autenticação no ERP:** na confirmação do laudo, o operador informa o usuário e a senha do Tasy no painel. O agente valida com o SQL de autenticação. A senha não fica guardada.
33. **Modelo de exame:** o mesmo do Yantra Cardio 3.0: tipo, 5 SQLs (consulta, autenticação, gravação, imagem e atualização), leituras (Parâmetro Interno, Parâmetro, Filtro e Informação Complementar) e opções de impressão. Os exames atuais entram por importação.
34. **Conexão com o banco do ERP:** cada cliente tem uma conexão só. Hoje todos os clientes usam Tasy com Oracle. A conexão tem um campo de tipo de banco, e o agente acessa o banco por uma camada própria. Assim, um ERP ou banco diferente no futuro exige só um driver novo no agente. Os SQLs ficam no cadastro do exame, independentes do código.
35. **Segurança dos SQLs:** só a Staff Weega altera SQLs e conexões. A API assina os parâmetros, e o agente rejeita parâmetros sem assinatura válida. Os valores do operador e do paciente entram por variáveis de ligação (bind), sem substituição de texto.

## Administrativo

O administrativo tem duas áreas, com login e permissões separados. Cada módulo indica a etapa em que entra.

### Área Staff Weega

- **Clientes**: cadastro das empresas clientes. (E1)
- **Usuários**: usuários da Weega (administrador, suporte) e dos clientes (operador). (E1)
- **Usuários → clientes**: vínculo de cada usuário a um ou mais clientes. (E1)
- **Sistemas**: YC PRT, YO PRT (impressora), YO SRC (captura de tela) e YO HUB (porta serial). (E3)
- **Licenças**: quantidade por cliente e por sistema. (E3)
- **Equipamentos**: equipamentos registrados, cliente, sistemas e estado da licença. (E2, E3)
- **Exames e parametrizações**: exames no modelo do Yantra Cardio 3.0 (5 SQLs e leituras), importação dos exames atuais, conexão Oracle do ERP, pastas, credenciais e regras regex. (E5, E8)
- **Log e auditoria**: registro completo das requisições da API e das ações dos usuários. (E1)

### Área Cliente

- **Login** no ambiente do cliente. (E1)
- **Execução dos exames**: início do exame num equipamento, lista de pacientes do ERP, conferência dos arquivos, escolha do paciente e do laudo, informações complementares, login no Tasy e confirmação. Papel: operador. (E6)
- **Interação com o agente**: estado dos equipamentos do cliente e do exame em andamento. (E6)

## Ciclo de exame

O ciclo começa quando o operador inicia o exame no painel e termina quando o laudo está gravado no ERP e o PDF está no file server. O fluxo segue o Yantra Cardio 3.0. A diferença é que a tela de Finalização agora fica no painel e o agente executa os SQLs.

1. O operador inicia o exame no painel, para um equipamento. A API envia o comando de início ao agente. O agente cria a pasta temporária \<pasta base>\\capturas\\\<nome do exame>\\\<timestamp> e vigia esta pasta.
2. A impressora Yantra Printer, a captura de tela e a porta serial gravam os arquivos do exame na pasta temporária.
3. O agente envia cada arquivo para o painel administrativo. Quando o arquivo está disponível no painel, o agente mostra uma notificação.
4. O painel pede ao agente a lista de pacientes. O agente executa o SQL de consulta no Oracle, com os Parâmetros e Filtros do operador, e devolve as linhas.
5. No painel, o operador escolhe o paciente e os arquivos, preenche as Informações Complementares e informa o usuário e a senha do Tasy.
6. A API envia ao agente o comando de gravação. O agente valida o usuário com o SQL de autenticação.
7. O agente copia cada PDF para a pasta final no file server:

   ```
   <PASTA do exame>\<PACIENTE>-<NASCIMENTO>\<aaaa>\<mm>\<dd>\<Exame>-<ATENDIMENTO>-<n>.pdf
   ```
8. Para cada PDF, o agente executa os SQLs de gravação (cria o laudo), de imagem (liga o caminho do PDF ao laudo) e de atualização (tira o pedido da fila).
9. O agente devolve ao painel os caminhos finais e os ids dos laudos. A API conclui o exame.
10. O agente cria uma pasta temporária nova e volta ao passo 2.

O caminho do PDF vai para o Tasy (tabela de imagens do laudo). Por isso a PASTA do exame precisa ser um caminho que o Tasy também acessa, por exemplo \\\\servidor\\yantra.

## Etapas e checklists

Carregando o progresso salvo…

## Perguntas em aberto

Nenhuma pergunta em aberto. A última pergunta, a hospedagem, foi decidida em 25/10/2026: AWS em São Paulo.

## Suposições

- Início em 26/10/2026. A equipe tem 2 pessoas: o desenvolvedor e o Claude Code.
- Semana de 5 dias úteis. Feriados descontados: 02/11, 20/11, 24/12, 25/12, 31/12, 01/01, 08/02, 09/02, 26/03 e 21/04.
- Em cada etapa, o administrativo e o agente andam em sequência.
- As estimativas incluem testes automatizados de cada etapa.
- A leitura USB fica fora deste cronograma. Ela entra depois da homologação da porta serial.
- Cada estação alcança o Oracle do ERP pela rede do hospital, como no Yantra Cardio 3.0. A equipe tem acesso a um Tasy de homologação para os testes da E6.
- No file server, a equipe de TI do cliente dá permissão de gravação às contas de computador do domínio (grupo Computadores do domínio) na pasta de destino.