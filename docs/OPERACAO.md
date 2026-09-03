# Operação, publicação e recuperação

Este guia reúne o procedimento seguro para colocar o Bistrô São Lauro PDV em produção e manter os dados recuperáveis. O sistema deve ser publicado na raiz do domínio ou subdomínio, pois as rotas da aplicação começam com `/`.

## Publicação

1. Confirme PHP 8.1 ou superior e as extensões `pdo_mysql`, `mbstring`, `fileinfo` e `json`.
2. Crie um banco MySQL/MariaDB e um usuário exclusivo com acesso somente a esse banco.
3. Envie os arquivos para a raiz do domínio, preservando `.htaccess` e todas as pastas.
4. Garanta escrita pelo PHP em `config`, `uploads` e `logs`; use normalmente `755` nas pastas e `644` nos arquivos.
5. Ative HTTPS antes de cadastrar usuários reais.
6. Abra `/install/`, informe o banco e crie o primeiro administrador. O arquivo `config/install.lock` bloqueará novas instalações.
7. Depois do primeiro acesso, cadastre os perfis, salões, mesas, categorias, complementos e produtos.

Nunca substitua `config/database.php`, `config/install.lock` ou o conteúdo de `uploads` ao publicar uma atualização. Como ainda não existe um executor de migrações, qualquer versão futura que altere o banco deverá incluir e seguir instruções SQL específicas antes da troca dos arquivos.

## Checklist antes de abrir o restaurante

- HTTPS válido e redirecionamento para a versão segura.
- Instalador redirecionando para o login.
- Um usuário exclusivo para cada perfil operacional.
- Celular do garçom e computador do balcão na mesma versão publicada.
- Som do balcão ativado por um clique após abrir a tela.
- Impressoras de cozinha, conta e recibo testadas pelo navegador.
- Pedido de teste concluído até o pagamento e conferido no histórico.

## Backup diário

Faça backup fora de `public_html` e, de preferência, também fora da própria hospedagem. O conjunto mínimo é:

- exportação SQL completa do banco;
- pasta `uploads`;
- `config/database.php` e `config/install.lock`, guardados de forma criptografada.

Na hospedagem compartilhada, use o **Assistente de Backup** do cPanel ou exporte o banco pelo phpMyAdmin. Nomeie os arquivos com data e hora, mantenha pelo menos sete cópias diárias e uma cópia mensal. Uma cópia só é confiável depois de ser restaurada em um ambiente de teste.

## Restauração

1. Retire temporariamente o PDV de operação e preserve uma cópia do estado atual.
2. Crie um banco vazio de recuperação; não teste a restauração diretamente sobre o banco em produção.
3. Importe a exportação SQL e aponte uma cópia de `config/database.php` para esse banco.
4. Restaure `uploads`, mantendo a proteção contra execução de arquivos PHP.
5. Valide login, mesas, último pedido, último pagamento e histórico.
6. Somente após essa conferência, troque o banco de produção ou repita a restauração na janela de manutenção.

Se apenas um arquivo de imagem estiver ausente, restaure somente esse arquivo. Não importe um banco antigo para corrigir um problema isolado sem avaliar quais vendas seriam perdidas.

## Homologação por perfil

### Garçom

- entrar somente em `/garcom/`;
- buscar mesa, filtrar salão e pesquisar produto sem acento;
- montar pedido com quantidade, observação e complementos;
- confirmar que uma falha de validação não ocupa a mesa;
- enviar dois pedidos para o mesmo atendimento e solicitar a conta.

### Balcão

- receber alerta visual e sonoro de pedido novo;
- atualizar os estados até entregue;
- abrir a mesa e conferir todos os pedidos e observações;
- rejeitar soma de pagamento incorreta;
- concluir pagamento e confirmar que a mesa foi liberada.

### Cozinha

- visualizar apenas pedidos que exigem produção;
- iniciar preparo e marcar como pronto;
- confirmar atualização automática após uma interrupção breve de rede.

### Administração

- cadastrar usuários e confirmar os redirecionamentos de cada perfil;
- cadastrar salão, mesa, produto e complemento obrigatório;
- impedir a desativação de salão com mesas vinculadas;
- consultar venda finalizada e impressões.

## Atualizações e monitoramento

Antes de toda atualização, gere um backup e confira o commit que será publicado. Faça a troca fora do horário de pico e repita o fluxo curto de homologação. Em caso de erro, consulte `logs/php-error.log`; nunca exponha esse arquivo pela web nem envie senhas ou dados pessoais em chamados públicos.
