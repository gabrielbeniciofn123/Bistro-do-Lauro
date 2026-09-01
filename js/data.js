/**
 * CARDÁPIO — BISTRÔ SÃO LAURO
 * Edite os itens abaixo com os pratos e bebidas reais da casa.
 * Cada categoria tem um array de itens com: nome, descricao, peso, preco e selo.
 */
const MENU_DATA = [
  {
    id: "porcoes",
    nome: "Porções & Entradas",
    subtitulo: "Para abrir o apetite e compartilhar à mesa.",
    icone: "utensils",
    itens: [
      { nome: "Porção 01", descricao: "Descrição a ser inserida pelo restaurante, destacando os principais atrativos da porção.", peso: "350 g", preco: 34.0, selo: null },
      { nome: "Porção 02", descricao: "Espaço reservado para uma descrição apetitosa e detalhada deste item.", peso: "400 g", preco: 42.0, selo: null },
      { nome: "Porção 03", descricao: "Texto descritivo pendente — em breve com ingredientes, preparo e diferenciais.", peso: "300 g", preco: 29.0, selo: null },
      { nome: "Porção 04", descricao: "Aguardando a descrição oficial da casa para este item do cardápio.", peso: "450 g", preco: 48.0, selo: "Escolha do Chef" }
    ]
  },
  {
    id: "carnes",
    nome: "Carnes",
    subtitulo: "Cortes selecionados, preparados no ponto perfeito.",
    icone: "meat",
    itens: [
      { nome: "Corte 01", descricao: "Descrição a ser inserida pelo restaurante, destacando os principais atrativos do corte.", peso: "300 g", preco: 68.0, selo: "Escolha do Chef" },
      { nome: "Corte 02", descricao: "Espaço reservado para uma descrição apetitosa e detalhada deste corte.", peso: "350 g", preco: 79.0, selo: null },
      { nome: "Corte 03", descricao: "Texto descritivo pendente — em breve com informações sobre o corte e preparo.", peso: "400 g", preco: 92.0, selo: null },
      { nome: "Corte 04", descricao: "Aguardando a descrição oficial da casa para este item do cardápio.", peso: "280 g", preco: 64.0, selo: null }
    ]
  },
  {
    id: "peixes",
    nome: "Peixes",
    subtitulo: "Frescor e leveza em receitas equilibradas e delicadas.",
    icone: "fish",
    itens: [
      { nome: "Peixe 01", descricao: "Descrição a ser inserida pelo restaurante, destacando os principais atrativos.", peso: "320 g", preco: 62.0, selo: null },
      { nome: "Peixe 02", descricao: "Espaço reservado para uma descrição apetitosa e detalhada deste peixe.", peso: "350 g", preco: 71.0, selo: "Mais Pedido" },
      { nome: "Peixe 03", descricao: "Texto descritivo pendente — em breve com ingredientes, preparo e diferenciais.", peso: "300 g", preco: 58.0, selo: null },
      { nome: "Peixe 04", descricao: "Aguardando a descrição oficial da casa para este item do cardápio.", peso: "380 g", preco: 79.0, selo: null }
    ]
  },
  {
    id: "tradicionais",
    nome: "Pratos Tradicionais",
    subtitulo: "Sabores clássicos de Minas que resgatam a mesa de família.",
    icone: "pot",
    itens: [
      { nome: "Prato 01", descricao: "Descrição a ser inserida pelo restaurante, destacando os principais atrativos.", peso: "400 g", preco: 45.0, selo: null },
      { nome: "Prato 02", descricao: "Espaço reservado para uma descrição apetitosa e detalhada deste prato.", peso: "450 g", preco: 52.0, selo: "Escolha do Chef" },
      { nome: "Prato 03", descricao: "Texto descritivo pendente — em breve com ingredientes, preparo e diferenciais.", peso: "420 g", preco: 49.0, selo: null },
      { nome: "Prato 04", descricao: "Aguardando a descrição oficial da casa para este item do cardápio.", peso: "400 g", preco: 47.0, selo: null }
    ]
  },
  {
    id: "cervejas",
    nome: "Cervejas",
    subtitulo: "Rótulos gelados para acompanhar qualquer prato.",
    icone: "mug",
    itens: [
      { nome: "Cerveja 01", descricao: "Descrição a ser adicionada, com detalhes sobre o rótulo e sabor.", peso: "600 ml", preco: 22.0, selo: null },
      { nome: "Cerveja 02", descricao: "Espaço reservado para a descrição desta cerveja.", peso: "355 ml", preco: 12.0, selo: null },
      { nome: "Cerveja 03", descricao: "Texto descritivo pendente para este item da carta de bebidas.", peso: "269 ml", preco: 9.0, selo: null },
      { nome: "Cerveja 04", descricao: "Aguardando informações oficiais da casa sobre esta bebida.", peso: "600 ml", preco: 24.0, selo: null }
    ]
  },
  {
    id: "refrigerantes",
    nome: "Refrigerantes & Água",
    subtitulo: "Clássicos refrescantes, com e sem gás.",
    icone: "bottle",
    itens: [
      { nome: "Bebida 01", descricao: "Descrição a ser adicionada, com detalhes sobre sabor e preparo.", peso: "310 ml", preco: 8.0, selo: null },
      { nome: "Bebida 02", descricao: "Espaço reservado para a descrição desta bebida.", peso: "600 ml", preco: 12.0, selo: null },
      { nome: "Bebida 03", descricao: "Texto descritivo pendente para este item da carta de bebidas.", peso: "500 ml", preco: 7.0, selo: null },
      { nome: "Bebida 04", descricao: "Aguardando informações oficiais da casa sobre esta bebida.", peso: "500 ml", preco: 8.0, selo: null }
    ]
  },
  {
    id: "sucos",
    nome: "Sucos",
    subtitulo: "Frutas selecionadas, preparados na hora.",
    icone: "juice",
    itens: [
      { nome: "Suco 01", descricao: "Descrição a ser adicionada, com detalhes sobre a fruta e preparo.", peso: "300 ml", preco: 14.0, selo: null },
      { nome: "Suco 02", descricao: "Espaço reservado para a descrição deste suco.", peso: "400 ml", preco: 16.0, selo: null },
      { nome: "Suco 03", descricao: "Texto descritivo pendente para este item da carta de bebidas.", peso: "300 ml", preco: 14.0, selo: null },
      { nome: "Suco 04", descricao: "Aguardando informações oficiais da casa sobre esta bebida.", peso: "500 ml", preco: 18.0, selo: null }
    ]
  },
  {
    id: "coqueteis",
    nome: "Coquetéis",
    subtitulo: "Autorais e clássicos, com equilíbrio e personalidade.",
    icone: "martini",
    itens: [
      { nome: "Coquetel 01", descricao: "Descrição a ser adicionada, com detalhes sobre o drink e seus ingredientes.", peso: "300 ml", preco: 28.0, selo: "Mais Pedido" },
      { nome: "Coquetel 02", descricao: "Espaço reservado para a descrição deste coquetel.", peso: "300 ml", preco: 32.0, selo: null },
      { nome: "Coquetel 03", descricao: "Texto descritivo pendente para este item da carta de bebidas.", peso: "250 ml", preco: 30.0, selo: null },
      { nome: "Coquetel 04", descricao: "Aguardando informações oficiais da casa sobre este drink.", peso: "300 ml", preco: 34.0, selo: null }
    ]
  },
  {
    id: "destilados",
    nome: "Destilados",
    subtitulo: "Doses selecionadas para apreciar com calma.",
    icone: "spirit",
    itens: [
      { nome: "Destilado 01", descricao: "Descrição a ser adicionada, com detalhes sobre o destilado.", peso: "Dose 50 ml", preco: 18.0, selo: null },
      { nome: "Destilado 02", descricao: "Espaço reservado para a descrição desta bebida.", peso: "Dose 50 ml", preco: 22.0, selo: null },
      { nome: "Destilado 03", descricao: "Texto descritivo pendente para este item da carta.", peso: "Dose 50 ml", preco: 26.0, selo: null },
      { nome: "Destilado 04", descricao: "Aguardando informações oficiais da casa sobre esta bebida.", peso: "Dose 50 ml", preco: 32.0, selo: null }
    ]
  },
  {
    id: "outras-bebidas",
    nome: "Outras Bebidas",
    subtitulo: "Mais opções para todos os momentos da mesa.",
    icone: "glass",
    itens: [
      { nome: "Bebida 05", descricao: "Descrição a ser adicionada, com detalhes sobre sabor e preparo.", peso: "300 ml", preco: 12.0, selo: null },
      { nome: "Bebida 06", descricao: "Espaço reservado para a descrição desta bebida.", peso: "250 ml", preco: 10.0, selo: null },
      { nome: "Bebida 07", descricao: "Texto descritivo pendente para este item da carta de bebidas.", peso: "300 ml", preco: 11.0, selo: null },
      { nome: "Bebida 08", descricao: "Aguardando informações oficiais da casa sobre esta bebida.", peso: "200 ml", preco: 9.0, selo: null }
    ]
  }
];
