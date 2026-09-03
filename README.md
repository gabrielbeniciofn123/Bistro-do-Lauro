# Bistrô São Lauro — Sistema PDV

Sistema de ponto de venda para restaurante desenvolvido em PHP 8, MySQL/MariaDB, HTML, CSS e JavaScript puro. O cardápio institucional existente foi preservado, e o acesso **Administrador** direciona ao PDV.

O planejamento e o estado atual das fases estão em [`ROADMAP.md`](ROADMAP.md).

## O que está incluído

- Login seguro com sessões e perfis de administrador, balcão, garçom e cozinha.
- Cadastro de usuários, salões, mesas, categorias, produtos, imagens e complementos.
- Abertura e controle de mesas com múltiplos pedidos na mesma comanda.
- Carrinho mobile para o garçom, observações e complementos.
- Proteção contra pedido duplicado por chave de idempotência.
- Painel do balcão atualizado a cada 2 segundos, alerta sonoro e fluxo de status.
- KDS da cozinha com cronômetro, início de preparo e pedido pronto.
- Cancelamento auditado de pedido ou item, sem apagar o histórico.
- Fechamento de mesa com taxa, desconto, acréscimo e pagamento dividido.
- Histórico real de vendas e páginas de impressão para cozinha, conta e comprovante.
- Instalador protegido para hospedagem compartilhada.

## Requisitos da hospedagem

- PHP 8.1 ou superior.
- Extensões PHP `pdo_mysql`, `mbstring`, `fileinfo` e `json`.
- MySQL 5.7+ ou MariaDB 10.4+ com tabelas InnoDB.
- Apache com suporte a `.htaccess` (padrão da HostGator).
- HTTPS habilitado no domínio.

O servidor não precisa de Node.js, React, Docker, WebSocket ou serviços externos.

## Instalação na HostGator

### 1. Criar o banco

1. Entre no Portal do Cliente da HostGator e abra o **cPanel** da hospedagem.
2. Procure **Bancos de dados MySQL®**.
3. Em **Criar novo banco**, informe um nome e clique em **Criar banco de dados**.
4. Em **Usuários MySQL**, crie um usuário e uma senha forte. Guarde essas três informações: nome completo do banco, usuário completo e senha.
5. Em **Adicionar usuário ao banco de dados**, selecione o usuário e o banco criados.
6. Marque **TODOS OS PRIVILÉGIOS** e confirme.

### 2. Enviar os arquivos

1. No cPanel, abra **Gerenciador de arquivos**.
2. Entre na pasta `public_html` do domínio.
3. Envie todo o conteúdo deste projeto mantendo as pastas.
4. Confirme que `database.sql`, `login.php`, `install/`, `config/`, `includes/`, `api/` e `assets/` estão dentro de `public_html`.
5. Nas permissões, use normalmente `755` para pastas e `644` para arquivos. As pastas `config`, `uploads` e `logs` precisam permitir escrita pelo PHP durante a instalação.

Não coloque os arquivos em uma pasta adicional dentro de `public_html`, a menos que queira acessar o sistema por um subdiretório.

### 3. Executar o instalador

1. Abra `https://SEU-DOMINIO.com.br/install/`.
2. Em **Servidor**, tente primeiro `localhost`, que é o padrão da HostGator.
3. Informe o nome completo do banco, o usuário MySQL e a senha criados no cPanel.
4. Informe o nome do restaurante, nome do administrador, login e uma senha com pelo menos 10 caracteres.
5. Os dados de demonstração são opcionais. Marque apenas se quiser quatro mesas e produtos simples para teste.
6. Clique em **Instalar sistema**.

O instalador cria todas as tabelas, grava `config/database.php`, cria o administrador e gera `config/install.lock`. Depois disso, `/install/` fica bloqueado automaticamente. Para reinstalar, é necessário remover manualmente o arquivo de bloqueio e usar um banco vazio; faça backup antes.

### 4. Ativar HTTPS

No cPanel, abra **SSL/TLS Status** e confirme que o AutoSSL está ativo para o domínio. O sistema usa cookies de sessão seguros quando está em HTTPS.

## Acessos

- Site público: `/`
- Login: `/login.php`
- Administrador: `/admin/`
- Garçom: `/garcom/`
- Balcão/caixa: `/balcao/`
- Cozinha: `/cozinha/`

Não existe senha padrão. O primeiro administrador é criado no instalador.

Os perfis operacionais são separados: o garçom monta e envia pedidos pela área `/garcom/`; o balcão acompanha pedidos, consulta mesas e realiza o fechamento pela área `/balcao/`. Uma mesa disponível só fica ocupada quando o primeiro pedido é enviado. O administrador pode supervisionar as duas áreas, cada uma mantendo sua própria navegação.

## Primeiro preparo do restaurante

1. Entre como administrador.
2. Em **Usuários**, cadastre pelo menos um garçom, um usuário de balcão e um de cozinha.
3. Em **Mesas e salões**, crie os setores e as mesas.
4. Em **Categorias**, cadastre as categorias do cardápio.
5. Em **Cardápio**, cadastre produtos, preços e disponibilidade.
6. Se necessário, crie grupos e opções em **Complementos** e relacione-os ao editar o produto.
7. Em **Configurações**, revise a taxa de serviço e mantenha o restaurante como aberto.

## Teste completo recomendado

1. Abra `/garcom/` no celular e `/balcao/` no computador.
2. No balcão, clique em **Ativar som dos pedidos** uma vez.
3. Como garçom, selecione uma mesa, adicione dois pratos e uma bebida, inclua uma observação e envie. A mesa deve continuar disponível até esse envio.
4. Confirme que o pedido aparece no balcão e na cozinha sem atualizar a página.
5. Na cozinha, marque **INICIAR PREPARO** e depois **PEDIDO PRONTO**.
6. No balcão, marque o pedido como entregue.
7. Volte à mesma mesa pelo garçom e envie um segundo pedido. O primeiro deve continuar visível.
8. Abra a mesa no balcão, confira a conta e clique em **Finalizar mesa**.
9. Teste um pagamento PIX ou divida entre duas formas. A soma precisa ser exatamente igual ao total.
10. Confirme que a mesa voltou para disponível e a venda apareceu em **Histórico**.

## Configuração manual do banco

O caminho recomendado é o instalador. Se precisar configurar manualmente:

1. Importe `database.sql` pelo **phpMyAdmin** no banco correto.
2. Copie `config/database.example.php` para `config/database.php`.
3. Preencha host, porta, banco, usuário e senha.
4. Substitua `app_secret` por uma chave aleatória longa.
5. Crie o administrador usando o instalador antes de gerar o bloqueio. Nunca grave uma senha em texto puro; o sistema usa `password_hash()`.

## Segurança e manutenção

- Faça backup diário do banco e da pasta `uploads`.
- Nunca publique `config/database.php` nem envie esse arquivo ao GitHub; ele já está no `.gitignore`.
- Pedidos, itens, pagamentos e cancelamentos não são apagados do histórico.
- Preços e complementos são recalculados no servidor a partir do banco.
- Alterações críticas usam transações e bloqueios de linha no MySQL.
- O carrinho ainda não enviado fica temporariamente no navegador se a conexão cair. O reenvio exige confirmação e reutiliza a mesma chave contra duplicidade.
- Consulte `logs/php-error.log` pelo Gerenciador de Arquivos somente quando precisar diagnosticar um erro. Essa pasta é bloqueada para acesso pela web.

## Desenvolvimento local

O **Live Server não executa PHP**. Ele serve apenas para conferir o `index.html` estático; as telas de login, instalador e PDV precisam do servidor PHP.

Com PHP 8 e MySQL instalados, abra a pasta do projeto no VS Code, use **Terminal → Executar Tarefa… → Iniciar PDV (PHP)** e acesse `http://127.0.0.1:8080`. O mesmo servidor pode ser iniciado manualmente com:

```bash
php -S 127.0.0.1:8080
```

Acesse `http://127.0.0.1:8080/install/` se ainda não tiver criado o banco pelo instalador.

O teste de integração em `tests/integration.php` só executa quando o nome do banco termina em `_test` e a variável `PDV_TEST_CONFIRM=1` está definida. Ele usa uma transação e desfaz os dados ao final.

```bash
php tests/access-separation.php
php tests/table-status-regression.php
PDV_TEST_CONFIRM=1 php tests/integration.php
```

Para executar a integração em outro banco sem alterar `config/database.php`, informe um banco isolado cujo nome termine em `_test`:

```bash
PDV_TEST_CONFIRM=1 PDV_TEST_DATABASE=bistro_pdv_test php tests/integration.php
```
