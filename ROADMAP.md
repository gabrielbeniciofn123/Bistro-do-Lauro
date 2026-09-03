# Roadmap do Bistrô São Lauro PDV

Este arquivo é a referência de continuidade do projeto. Cada fase deve terminar com testes, um commit próprio e `push` para `origin/main`.

## Objetivo do produto

Entregar um PDV de restaurante simples para celular, tablet e computador, com responsabilidades separadas entre garçom, balcão, cozinha e administração, mantendo mesas, pedidos e pagamentos consistentes no banco de dados.

## Fases

### 1. Separação entre garçom e balcão — concluída

- Rotas, layouts e estados locais separados por área e usuário.
- Autorizações centralizadas no backend.
- Balcão impedido de entrar no fluxo de criação do garçom.
- Commit: `0ee282b`.

### 2. Estado das mesas e primeiro pedido — concluída

- Mesa permanece disponível enquanto o primeiro pedido é montado.
- Sessão e ocupação são criadas atomicamente no primeiro envio.
- Pedidos adicionais usam a sessão já aberta.
- Balcão atualiza a grade de mesas a cada dois segundos.
- Commit: `b33a678`.

### 3. Mesas, salões, pedidos por mesa e pagamento — concluída

Objetivo prioritário: corrigir o cadastro de mesas e salões e oferecer no balcão uma visão clara de cada mesa, seus pedidos e a opção segura de finalizar o pagamento.

Critérios de conclusão:

- impedir estados de mesa incompatíveis com as sessões reais;
- impedir a desativação de salão que ainda possui mesas vinculadas;
- validar que novas mesas sejam vinculadas somente a salões ativos;
- agrupar as mesas por salão no balcão;
- mostrar garçom, quantidade de pedidos, horário, subtotal e situação em cada mesa;
- mostrar itens, observações, complementos e status de cada pedido nos detalhes;
- disponibilizar “Finalizar pagamento” somente quando não houver pedido em produção;
- validar fechamento e liberação da mesa por teste de integração.

Entrega registrada no commit com a mensagem `feat: melhora gestão de mesas e pagamentos`.

### 4. Alertas e operação em tempo real — concluída

- polling compartilhado sem requisições sobrepostas no garçom, balcão e cozinha;
- falhas de sincronização e recuperação visíveis sem interromper a operação;
- aviso visual e sonoro no balcão para pedidos novos;
- contexto de áudio reutilizado e liberado por interação do usuário;
- atualização automática das mesas do garçom preservando busca e filtro de salão;
- cursor de eventos validado por teste de integração entre os três perfis operacionais.

Entrega registrada no commit com a mensagem `feat: torna atualizacao em tempo real resiliente`.

### 5. Usabilidade e homologação — planejada

- revisar o cardápio mobile e os fluxos de erro;
- executar testes completos por perfil;
- revisar instalação, backup e publicação em hospedagem.

## Regras de entrega

- Não misturar objetivos de fases diferentes no mesmo commit.
- Executar testes relacionados antes do commit.
- Fazer `push` do commit antes de considerar a fase concluída.
- Atualizar este roadmap quando um objetivo ou prioridade mudar.
