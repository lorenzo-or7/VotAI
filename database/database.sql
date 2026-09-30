-- =====================================================================
--  VOTAI (Voto + AI) — Estrutura do banco de dados
--  MySQL 8.0+ / MariaDB 10.6+  ·  charset utf8mb4
--
--  Como importar:
--    mysql -u root -p < database/database.sql
--  (ou pelo phpMyAdmin: aba "Importar")
--
--  Contém: criação das tabelas, relacionamentos (FKs), índices e
--  DADOS FICTÍCIOS de demonstração (Candidato A/B/C/D, Partido Alfa...).
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS votai
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
USE votai;

DROP TABLE IF EXISTS propostas_historico;
DROP TABLE IF EXISTS candidatura_partidos;
DROP TABLE IF EXISTS fontes;
DROP TABLE IF EXISTS propostas;
DROP TABLE IF EXISTS subcategorias;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS documentos;
DROP TABLE IF EXISTS candidatos;
DROP TABLE IF EXISTS partidos;
DROP TABLE IF EXISTS eleicoes;
DROP TABLE IF EXISTS usuarios_admin;

-- ---------------------------------------------------------------------
-- Eleições (permite manter várias eleições no mesmo banco)
-- ---------------------------------------------------------------------
CREATE TABLE eleicoes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome            VARCHAR(160) NOT NULL,
  ano             SMALLINT UNSIGNED NOT NULL,
  turno           TINYINT UNSIGNED NOT NULL DEFAULT 1,
  cargo_principal VARCHAR(120) NOT NULL,
  abrangencia     VARCHAR(60)  NOT NULL DEFAULT 'Nacional',
  data_eleicao    DATE NULL,
  ativa           TINYINT(1) NOT NULL DEFAULT 1,
  criado_em       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_eleicoes_ativa (ativa, ano)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Partidos (logo = caminho do arquivo, ex.: assets/images/partidos/partido-01.svg)
-- ---------------------------------------------------------------------
CREATE TABLE partidos (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(160) NOT NULL,
  sigla      VARCHAR(20)  NOT NULL,
  numero     VARCHAR(5)   NULL,
  logo       VARCHAR(255) NULL,
  criado_em  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_partidos_sigla (sigla)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Candidatos (foto = caminho do arquivo, ex.: assets/images/candidatos/candidato-01.webp)
-- ---------------------------------------------------------------------
CREATE TABLE candidatos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  eleicao_id    INT UNSIGNED NOT NULL,
  partido_id    INT UNSIGNED NULL,
  nome          VARCHAR(180) NOT NULL,
  nome_urna     VARCHAR(120) NOT NULL,
  slug          VARCHAR(140) NOT NULL,
  numero        VARCHAR(8)   NULL,
  cargo         VARCHAR(120) NOT NULL,
  uf            CHAR(2)      NULL,
  foto          VARCHAR(255) NULL,
  foto_fonte    VARCHAR(255) NULL COMMENT 'Origem da foto (ex.: DivulgaCandContas/TSE)',
  demonstracao  TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = registro fictício',
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  sq_candidato         BIGINT UNSIGNED NULL COMMENT 'SQ_CANDIDATO do TSE (chave com fotos e PDFs)',
  tipo_agremiacao      VARCHAR(40)  NULL,
  coligacao_nome       VARCHAR(200) NULL,
  coligacao_composicao TEXT NULL,
  federacao_nome       VARCHAR(200) NULL,
  vice_sq              BIGINT UNSIGNED NULL,
  vice_nome            VARCHAR(180) NULL,
  vice_nome_urna       VARCHAR(120) NULL,
  vice_partido_sigla   VARCHAR(20)  NULL,
  vice_foto            VARCHAR(255) NULL,
  atualizado_tse       DATETIME NULL,
  criado_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_candidatos_slug (slug),
  UNIQUE KEY uq_candidatos_sq (sq_candidato),
  KEY idx_candidatos_eleicao (eleicao_id, ativo),
  CONSTRAINT fk_candidatos_eleicao FOREIGN KEY (eleicao_id) REFERENCES eleicoes(id) ON UPDATE CASCADE,
  CONSTRAINT fk_candidatos_partido FOREIGN KEY (partido_id) REFERENCES partidos(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Partidos ligados a cada candidatura (titular + coligação/federação)
-- ---------------------------------------------------------------------
CREATE TABLE candidatura_partidos (
  candidato_id INT UNSIGNED NOT NULL,
  partido_id   INT UNSIGNED NOT NULL,
  papel        ENUM('titular','coligacao') NOT NULL DEFAULT 'coligacao',
  federacao    VARCHAR(200) NULL,
  ordem        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (candidato_id, partido_id),
  CONSTRAINT fk_cp_candidato FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
  CONSTRAINT fk_cp_partido   FOREIGN KEY (partido_id)   REFERENCES partidos(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Documentos oficiais (plano de governo, programa etc.)
-- ---------------------------------------------------------------------
CREATE TABLE documentos (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidato_id     INT UNSIGNED NOT NULL,
  eleicao_id       INT UNSIGNED NOT NULL,
  titulo           VARCHAR(220) NOT NULL,
  tipo             ENUM('plano_governo','programa_partidario','documento_complementar','outro') NOT NULL DEFAULT 'plano_governo',
  arquivo          VARCHAR(255) NULL COMMENT 'Caminho local do PDF',
  url_origem       VARCHAR(500) NULL COMMENT 'Link para a fonte oficial (ex.: TSE)',
  total_paginas    SMALLINT UNSIGNED NULL,
  data_publicacao  DATE NULL,
  hash_sha256      CHAR(64) NULL COMMENT 'Integridade do arquivo analisado',
  demonstracao     TINYINT(1) NOT NULL DEFAULT 0,
  criado_em        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_documentos_candidato (candidato_id),
  CONSTRAINT fk_documentos_candidato FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_documentos_eleicao   FOREIGN KEY (eleicao_id)   REFERENCES eleicoes(id)   ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Categorias (temas) e subcategorias
-- palavras_chave: sinônimos usados pela busca (separados por vírgula)
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(80)  NOT NULL,
  nome            VARCHAR(120) NOT NULL,
  descricao       TEXT NULL,
  palavras_chave  TEXT NULL,
  ordem           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ativo           TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_categorias_slug (slug),
  KEY idx_categorias_ordem (ativo, ordem)
) ENGINE=InnoDB;

CREATE TABLE subcategorias (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  categoria_id  INT UNSIGNED NOT NULL,
  slug          VARCHAR(80)  NOT NULL,
  nome          VARCHAR(120) NOT NULL,
  ordem         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_sub_cat_slug (categoria_id, slug),
  CONSTRAINT fk_sub_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Usuários do painel administrativo
-- ---------------------------------------------------------------------
CREATE TABLE usuarios_admin (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  senha_hash    VARCHAR(255) NOT NULL,
  papel         ENUM('admin','revisor') NOT NULL DEFAULT 'revisor',
  ativo         TINYINT(1) NOT NULL DEFAULT 1,
  ultimo_login  DATETIME NULL,
  criado_em     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Propostas
-- Campos opcionais (meta, prazo, custo, financiamento) ficam NULL quando
-- NÃO aparecem no documento — a interface exibe "Não informado no documento."
-- Somente status = 'publicada' aparece no site público.
-- ---------------------------------------------------------------------
CREATE TABLE propostas (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  candidato_id       INT UNSIGNED NOT NULL,
  categoria_id       INT UNSIGNED NOT NULL,
  subcategoria_id    INT UNSIGNED NULL,
  resumo             TEXT NOT NULL COMMENT 'Resumo elaborado pela plataforma',
  acao               VARCHAR(500) NOT NULL COMMENT 'Ação proposta',
  meta               VARCHAR(500) NULL,
  prazo              VARCHAR(255) NULL,
  custo              VARCHAR(255) NULL,
  financiamento      VARCHAR(500) NULL,
  status             ENUM('pendente','em_revisao','aprovada','rejeitada','publicada') NOT NULL DEFAULT 'pendente',
  observacao_revisao TEXT NULL,
  revisado_por       INT UNSIGNED NULL,
  revisado_em        DATETIME NULL,
  criado_em          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_prop_publico   (status, categoria_id, candidato_id),
  KEY idx_prop_candidato (candidato_id, status),
  KEY idx_prop_sub       (subcategoria_id),
  CONSTRAINT fk_prop_candidato FOREIGN KEY (candidato_id)    REFERENCES candidatos(id)     ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_prop_categoria FOREIGN KEY (categoria_id)    REFERENCES categorias(id)     ON UPDATE CASCADE,
  CONSTRAINT fk_prop_sub       FOREIGN KEY (subcategoria_id) REFERENCES subcategorias(id)  ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_prop_revisor   FOREIGN KEY (revisado_por)    REFERENCES usuarios_admin(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Fontes: liga cada proposta ao trecho original do documento
-- (uma proposta pode ter mais de uma fonte)
-- ---------------------------------------------------------------------
CREATE TABLE fontes (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposta_id      INT UNSIGNED NOT NULL,
  documento_id     INT UNSIGNED NOT NULL,
  pagina           SMALLINT UNSIGNED NULL,
  localizacao      VARCHAR(255) NULL COMMENT 'Seção/capítulo no documento',
  trecho_original  TEXT NOT NULL COMMENT 'Texto literal extraído do documento',
  criado_em        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fontes_proposta (proposta_id),
  KEY idx_fontes_documento (documento_id),
  CONSTRAINT fk_fontes_proposta  FOREIGN KEY (proposta_id)  REFERENCES propostas(id)  ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_fontes_documento FOREIGN KEY (documento_id) REFERENCES documentos(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Histórico de mudanças de status (trilha de auditoria da revisão)
-- ---------------------------------------------------------------------
CREATE TABLE propostas_historico (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  proposta_id     INT UNSIGNED NOT NULL,
  usuario_id      INT UNSIGNED NULL,
  status_anterior VARCHAR(20) NULL,
  status_novo     VARCHAR(20) NOT NULL,
  observacao      TEXT NULL,
  criado_em       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hist_proposta (proposta_id),
  CONSTRAINT fk_hist_proposta FOREIGN KEY (proposta_id) REFERENCES propostas(id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_usuario  FOREIGN KEY (usuario_id)  REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Canal de correções (relatos do público; o IP nunca é guardado, só um hash apagado em 30 dias)
CREATE TABLE IF NOT EXISTS correcoes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  proposta_id INT UNSIGNED NULL,
  tipo VARCHAR(20) NOT NULL,
  pagina VARCHAR(40) NULL,
  descricao TEXT NOT NULL,
  email VARCHAR(190) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'nova',
  nota_interna TEXT NULL,
  ip_hash CHAR(64) NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status, criado_em),
  KEY idx_ip (ip_hash, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limite de tentativas de login (por IP e por conta)
CREATE TABLE IF NOT EXISTS login_tentativas (
  chave CHAR(64) NOT NULL PRIMARY KEY,
  falhas INT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_ate DATETIME NULL,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Usuário administrador inicial
--   e-mail: admin@votai.local   senha: Votai@2026
--   Essa senha só funciona no computador local (XAMPP) e o painel obriga a trocá-la
--   no primeiro acesso. Na hospedagem ela é recusada.
-- ---------------------------------------------------------------------
INSERT INTO usuarios_admin (id, nome, email, senha_hash, papel) VALUES
(1, 'Administrador', 'admin@votai.local', '$2y$12$wcCtZaB0o9l86W33W4Y8jO9xTFXj5HiTUsHE1qCYyJhRKmAztPxqO', 'admin');

-- ---------------------------------------------------------------
-- DADOS FICTÍCIOS DE DEMONSTRAÇÃO
-- Nenhum conteúdo abaixo corresponde a pessoas, partidos ou propostas reais.
-- ---------------------------------------------------------------

INSERT INTO eleicoes (id, nome, ano, turno, cargo_principal, abrangencia, data_eleicao, ativa) VALUES
(1, 'Eleições Gerais 2026 (demonstração)', 2026, 1, 'Presidente da República', 'Nacional', '2026-10-04', 1);

INSERT INTO partidos (id, nome, sigla, numero, logo) VALUES
(1, 'Partido Alfa', 'ALFA', '10', 'assets/images/partidos/partido-01.svg'),
(2, 'Partido Beta', 'BETA', '20', 'assets/images/partidos/partido-02.svg'),
(3, 'Partido Gama', 'GAMA', '30', 'assets/images/partidos/partido-03.svg'),
(4, 'Partido Delta', 'DELTA', '40', 'assets/images/partidos/partido-04.svg');

INSERT INTO candidatos (id, eleicao_id, partido_id, nome, nome_urna, slug, numero, cargo, uf, foto, demonstracao) VALUES
(1, 1, 1, 'Candidato A (Demonstração)', 'Candidato A', 'candidato-a', '10', 'Presidente da República', NULL, 'assets/images/candidatos/candidato-01.webp', 1),
(2, 1, 2, 'Candidato B (Demonstração)', 'Candidato B', 'candidato-b', '20', 'Presidente da República', NULL, 'assets/images/candidatos/candidato-02.webp', 1),
(3, 1, 3, 'Candidato C (Demonstração)', 'Candidato C', 'candidato-c', '30', 'Presidente da República', NULL, 'assets/images/candidatos/candidato-03.webp', 1),
(4, 1, 4, 'Candidato D (Demonstração)', 'Candidato D', 'candidato-d', '40', 'Presidente da República', NULL, 'assets/images/candidatos/candidato-04.webp', 1);

INSERT INTO categorias (id, slug, nome, descricao, palavras_chave, ordem) VALUES
(1, 'saude', 'Saúde', 'Atenção primária, hospitais, saúde mental, acesso a medicamentos e tempo de espera no sistema público.', 'sus, hospital, hospitais, médico, médicos, posto, ubs, remédio, medicamento, fila, cirurgia, vacina, saude', 1),
(2, 'educacao', 'Educação', 'Da creche à universidade: acesso, permanência, qualidade do ensino, formação e carreira docente.', 'escola, escolas, professor, professores, universidade, universidades, creche, ensino, aluno, faculdade, enem', 2),
(3, 'economia', 'Economia', 'Inflação, juros, contas públicas, crescimento, indústria e comércio.', 'inflação, juros, pib, dívida, gastos, fiscal, indústria, comércio, crescimento, investimento', 3),
(4, 'emprego-e-renda', 'Emprego e renda', 'Geração de vagas, qualificação profissional, empreendedorismo e renda do trabalho.', 'emprego, empregos, desemprego, trabalho, salário, renda, qualificação, mei, carteira', 4),
(5, 'seguranca-publica', 'Segurança pública', 'Polícias, crime organizado, sistema prisional, fronteiras e enfrentamento à violência contra a mulher.', 'segurança, violência, crime, polícia, policial, presídio, prisão, tráfico, facção, homicídio, feminicídio, fronteira', 5),
(6, 'habitacao', 'Habitação', 'Moradia popular, regularização fundiária, aluguel social e urbanização de comunidades.', 'moradia, casa, casas, habitação, aluguel, favela, comunidade, imóvel, minha casa', 6),
(7, 'infraestrutura', 'Infraestrutura', 'Rodovias, ferrovias, saneamento, mobilidade urbana, portos e aeroportos.', 'estrada, rodovia, ferrovia, trem, saneamento, esgoto, água, ônibus, metrô, porto, aeroporto, obra, obras', 7),
(8, 'meio-ambiente', 'Meio ambiente', 'Desmatamento, clima, biomas, resíduos sólidos e recursos hídricos.', 'meio ambiente, desmatamento, amazônia, clima, floresta, queimada, lixo, reciclagem, rio, água, carbono', 8),
(9, 'impostos', 'Impostos', 'Reforma tributária, imposto de renda, tributação do consumo e simplificação.', 'imposto, impostos, tributo, tributária, ir, imposto de renda, icms, iva, taxa, carga tributária', 9),
(10, 'assistencia-social', 'Assistência social', 'Transferência de renda, segurança alimentar, pessoas idosas e pessoas com deficiência.', 'bolsa, auxílio, fome, cesta, pobreza, idoso, idosos, deficiência, cadastro único, benefício', 10),
(11, 'tecnologia', 'Tecnologia', 'Inclusão digital, ciência e inovação, governo digital e proteção de dados.', 'internet, tecnologia, digital, inovação, ciência, pesquisa, dados, inteligência artificial, conectividade, 5g', 11),
(12, 'gestao-publica', 'Gestão pública', 'Transparência, integridade, eficiência do serviço público e relação entre União, estados e municípios.', 'corrupção, transparência, gestão, servidor, servidores, concurso, ministérios, burocracia, federalismo', 12),
(13, 'relacoes-exteriores', 'Relações exteriores', 'Comércio internacional, integração regional, diplomacia e cooperação.', 'exterior, internacional, mercosul, diplomacia, exportação, acordo, países, relações exteriores', 13),
(14, 'energia', 'Energia', 'Fontes renováveis, petróleo e gás, tarifas e transição energética.', 'energia, luz, conta de luz, tarifa, petróleo, gás, combustível, gasolina, solar, eólica, hidrelétrica', 14);

INSERT INTO subcategorias (id, categoria_id, slug, nome, ordem) VALUES
(1, 1, 'atencao-basica', 'Atenção básica', 1),
(2, 1, 'hospitais', 'Hospitais e especialidades', 2),
(3, 1, 'saude-mental', 'Saúde mental', 3),
(4, 1, 'medicamentos', 'Medicamentos', 4),
(5, 1, 'filas-e-regulacao', 'Filas e regulação', 5),
(6, 2, 'educacao-infantil', 'Educação infantil', 1),
(7, 2, 'ensino-fundamental', 'Ensino fundamental', 2),
(8, 2, 'ensino-medio', 'Ensino médio', 3),
(9, 2, 'universidades', 'Universidades', 4),
(10, 2, 'professores', 'Professores', 5),
(11, 3, 'inflacao-e-juros', 'Inflação e juros', 1),
(12, 3, 'contas-publicas', 'Contas públicas', 2),
(13, 3, 'crescimento', 'Crescimento', 3),
(14, 3, 'comercio-e-industria', 'Comércio e indústria', 4),
(15, 4, 'geracao-de-empregos', 'Geração de empregos', 1),
(16, 4, 'qualificacao', 'Qualificação profissional', 2),
(17, 4, 'empreendedorismo', 'Empreendedorismo', 3),
(18, 4, 'salario-e-renda', 'Salário e renda', 4),
(19, 5, 'policia', 'Polícia', 1),
(20, 5, 'crime-organizado', 'Crime organizado', 2),
(21, 5, 'sistema-prisional', 'Sistema prisional', 3),
(22, 5, 'fronteiras', 'Fronteiras', 4),
(23, 5, 'violencia-contra-a-mulher', 'Violência contra a mulher', 5),
(24, 6, 'moradia-popular', 'Moradia popular', 1),
(25, 6, 'regularizacao-fundiaria', 'Regularização fundiária', 2),
(26, 6, 'aluguel-social', 'Aluguel social', 3),
(27, 6, 'urbanizacao', 'Urbanização de comunidades', 4),
(28, 7, 'rodovias', 'Rodovias', 1),
(29, 7, 'ferrovias', 'Ferrovias', 2),
(30, 7, 'saneamento', 'Saneamento', 3),
(31, 7, 'mobilidade-urbana', 'Mobilidade urbana', 4),
(32, 7, 'portos-e-aeroportos', 'Portos e aeroportos', 5),
(33, 8, 'desmatamento', 'Desmatamento', 1),
(34, 8, 'clima', 'Clima e emissões', 2),
(35, 8, 'biomas', 'Biomas', 3),
(36, 8, 'residuos', 'Resíduos', 4),
(37, 8, 'recursos-hidricos', 'Recursos hídricos', 5),
(38, 9, 'reforma-tributaria', 'Reforma tributária', 1),
(39, 9, 'imposto-de-renda', 'Imposto de renda', 2),
(40, 9, 'simplificacao', 'Simplificação', 3),
(41, 10, 'transferencia-de-renda', 'Transferência de renda', 1),
(42, 10, 'combate-a-fome', 'Combate à fome', 2),
(43, 10, 'pessoas-idosas', 'Pessoas idosas', 3),
(44, 10, 'pessoas-com-deficiencia', 'Pessoas com deficiência', 4),
(45, 11, 'inclusao-digital', 'Inclusão digital', 1),
(46, 11, 'pesquisa-e-inovacao', 'Pesquisa e inovação', 2),
(47, 11, 'governo-digital', 'Governo digital', 3),
(48, 11, 'protecao-de-dados', 'Proteção de dados', 4),
(49, 12, 'transparencia', 'Transparência', 1),
(50, 12, 'combate-a-corrupcao', 'Combate à corrupção', 2),
(51, 12, 'servico-publico', 'Serviço público', 3),
(52, 12, 'federalismo', 'Federalismo', 4),
(53, 13, 'comercio-internacional', 'Comércio internacional', 1),
(54, 13, 'integracao-regional', 'Integração regional', 2),
(55, 13, 'diplomacia', 'Diplomacia', 3),
(56, 14, 'renovaveis', 'Fontes renováveis', 1),
(57, 14, 'petroleo-e-gas', 'Petróleo e gás', 2),
(58, 14, 'tarifas', 'Tarifas de energia', 3),
(59, 14, 'transicao-energetica', 'Transição energética', 4);

INSERT INTO documentos (id, candidato_id, eleicao_id, titulo, tipo, arquivo, url_origem, total_paginas, data_publicacao, demonstracao) VALUES
(1, 1, 1, 'Plano de Governo — Candidato A (documento fictício)', 'plano_governo', 'uploads/documentos/plano-candidato-a-demo.pdf', NULL, 35, '2026-08-15', 1),
(2, 2, 1, 'Plano de Governo — Candidato B (documento fictício)', 'plano_governo', 'uploads/documentos/plano-candidato-b-demo.pdf', NULL, 40, '2026-08-15', 1),
(3, 3, 1, 'Plano de Governo — Candidato C (documento fictício)', 'plano_governo', 'uploads/documentos/plano-candidato-c-demo.pdf', NULL, 43, '2026-08-15', 1),
(4, 4, 1, 'Plano de Governo — Candidato D (documento fictício)', 'plano_governo', 'uploads/documentos/plano-candidato-d-demo.pdf', NULL, 36, '2026-08-15', 1);

INSERT INTO propostas (id, candidato_id, categoria_id, subcategoria_id, resumo, acao, meta, prazo, custo, financiamento, status, revisado_por, revisado_em) VALUES
(1, 1, 1, 1, 'O plano propõe contratar agentes comunitários de saúde para ampliar a cobertura da Estratégia Saúde da Família.', 'Contratar agentes comunitários de saúde para ampliar a cobertura da Estratégia Saúde da Família', 'Cobertura de 85% da população pela Saúde da Família', NULL, NULL, 'Repasses federais aos municípios', 'publicada', 1, NOW()),
(2, 1, 1, 5, 'O plano propõe realizar mutirões nacionais de cirurgias eletivas em parceria com estados.', 'Realizar mutirões nacionais de cirurgias eletivas em parceria com estados', NULL, 'Primeiro ano de mandato', 'R$ 800 milhões', 'Fundo Nacional de Saúde', 'publicada', 1, NOW()),
(3, 1, 2, 6, 'O plano propõe construir e equipar novas creches em parceria com os municípios.', 'Construir e equipar novas creches em parceria com os municípios', NULL, NULL, NULL, NULL, 'pendente', NULL, NULL),
(4, 1, 2, 8, 'O plano propõe integrar cursos técnicos ao ensino médio nas redes estaduais.', 'Integrar cursos técnicos ao ensino médio nas redes estaduais', '1 milhão de matrículas em ensino médio técnico', NULL, NULL, NULL, 'publicada', 1, NOW()),
(5, 1, 2, 9, 'O plano propõe ampliar vagas em cursos de graduação noturnos.', 'Ampliar vagas em cursos de graduação noturnos', '200 mil novas vagas noturnas', NULL, NULL, NULL, 'publicada', 1, NOW()),
(6, 1, 2, 10, 'O plano propõe criar programa nacional de formação continuada para professores da educação básica.', 'Criar programa nacional de formação continuada para professores da educação básica', '500 mil professores formados', NULL, NULL, NULL, 'publicada', 1, NOW()),
(7, 1, 3, 11, 'O plano propõe rever a composição do conselho que define a meta de inflação.', 'Rever a composição do conselho que define a meta de inflação', NULL, 'Primeiro ano de mandato', NULL, NULL, 'publicada', 1, NOW()),
(8, 1, 3, 12, 'O plano propõe adotar meta de resultado primário crescente ao longo do mandato.', 'Adotar meta de resultado primário crescente ao longo do mandato', 'Superávit primário de 1% do PIB', 'Até o quarto ano', NULL, NULL, 'publicada', 1, NOW()),
(9, 1, 3, 13, 'O plano propõe reduzir o custo do crédito para micro e pequenas empresas por meio de bancos públicos.', 'Reduzir o custo do crédito para micro e pequenas empresas por meio de bancos públicos', NULL, NULL, NULL, 'Fundos garantidores', 'publicada', 1, NOW()),
(10, 1, 3, 14, 'O plano propõe lançar política de modernização do parque industrial com foco em produtividade.', 'Lançar política de modernização do parque industrial com foco em produtividade', NULL, 'Dez anos', NULL, 'BNDES e fundos setoriais', 'publicada', 1, NOW()),
(11, 1, 4, 15, 'O plano propõe criar frentes de trabalho em obras de infraestrutura local.', 'Criar frentes de trabalho em obras de infraestrutura local', '500 mil vagas temporárias', 'Dois anos', 'R$ 12 bilhões', 'Orçamento federal de investimentos', 'publicada', 1, NOW()),
(12, 1, 4, 17, 'O plano propõe ampliar o limite de faturamento do microempreendedor individual (MEI).', 'Ampliar o limite de faturamento do microempreendedor individual (MEI)', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(13, 1, 4, 18, 'O plano propõe ampliar o seguro-desemprego para trabalhadores por aplicativo.', 'Ampliar o seguro-desemprego para trabalhadores por aplicativo', NULL, NULL, NULL, 'Fundo de Amparo ao Trabalhador', 'publicada', 1, NOW()),
(14, 1, 5, 21, 'O plano propõe ampliar programas de trabalho e estudo para pessoas privadas de liberdade.', 'Ampliar programas de trabalho e estudo para pessoas privadas de liberdade', '50% da população prisional em trabalho ou estudo', NULL, NULL, NULL, 'publicada', 1, NOW()),
(15, 1, 5, 22, 'O plano propõe ampliar o monitoramento das fronteiras terrestres com radares e drones.', 'Ampliar o monitoramento das fronteiras terrestres com radares e drones', NULL, NULL, NULL, 'Orçamento da defesa', 'publicada', 1, NOW()),
(16, 1, 5, 23, 'O plano propõe garantir atendimento 24 horas em delegacias especializadas de atendimento à mulher.', 'Garantir atendimento 24 horas em delegacias especializadas de atendimento à mulher', NULL, NULL, NULL, 'Fundo Nacional de Segurança Pública', 'publicada', 1, NOW()),
(17, 1, 6, 26, 'O plano propõe criar programa federal de aluguel social com participação de estados e municípios.', 'Criar programa federal de aluguel social com participação de estados e municípios', NULL, NULL, NULL, 'Cofinanciamento federativo', 'publicada', 1, NOW()),
(18, 1, 6, 27, 'O plano propõe urbanizar comunidades com obras de drenagem, iluminação e pavimentação.', 'Urbanizar comunidades com obras de drenagem, iluminação e pavimentação', NULL, 'Oito anos', NULL, 'Programa de Aceleração de Investimentos', 'publicada', 1, NOW()),
(19, 1, 7, 28, 'O plano propõe recuperar trechos de rodovias federais classificados como ruins ou péssimos.', 'Recuperar trechos de rodovias federais classificados como ruins ou péssimos', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(20, 1, 7, 29, 'O plano propõe concluir obras ferroviárias de integração entre regiões produtoras e portos.', 'Concluir obras ferroviárias de integração entre regiões produtoras e portos', NULL, 'Oito anos', NULL, 'Parcerias público-privadas', 'publicada', 1, NOW()),
(21, 1, 7, 31, 'O plano propõe financiar corredores de ônibus e sistemas sobre trilhos em regiões metropolitanas.', 'Financiar corredores de ônibus e sistemas sobre trilhos em regiões metropolitanas', NULL, NULL, 'R$ 20 bilhões', NULL, 'publicada', 1, NOW()),
(22, 1, 8, 33, 'O plano propõe zerar o desmatamento ilegal na Amazônia com ampliação da fiscalização.', 'Zerar o desmatamento ilegal na Amazônia com ampliação da fiscalização', 'Desmatamento ilegal zero', 'Até 2030', NULL, NULL, 'publicada', 1, NOW()),
(23, 1, 8, 34, 'O plano propõe criar mercado regulado de carbono com participação de setores industriais.', 'Criar mercado regulado de carbono com participação de setores industriais', NULL, 'Dois anos', NULL, NULL, 'publicada', 1, NOW()),
(24, 1, 8, 35, 'O plano propõe criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga.', 'Criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga', '12 milhões de hectares recuperados', 'Até 2030', NULL, 'Fundos climáticos e cooperação internacional', 'publicada', 1, NOW()),
(25, 1, 8, 36, 'O plano propõe criar incentivo a cooperativas de catadores de materiais recicláveis.', 'Criar incentivo a cooperativas de catadores de materiais recicláveis', NULL, NULL, NULL, NULL, 'em_revisao', NULL, NULL),
(26, 1, 8, 37, 'O plano propõe concluir obras de segurança hídrica no Semiárido.', 'Concluir obras de segurança hídrica no Semiárido', NULL, 'Quatro anos', NULL, 'Orçamento federal', 'publicada', 1, NOW()),
(27, 1, 9, 39, 'O plano propõe ampliar a faixa de isenção do imposto de renda da pessoa física.', 'Ampliar a faixa de isenção do imposto de renda da pessoa física', NULL, 'Dois anos', NULL, 'Compensação por tributação de rendimentos hoje isentos', 'publicada', 1, NOW()),
(28, 1, 9, 40, 'O plano propõe unificar obrigações acessórias das empresas em declaração única digital.', 'Unificar obrigações acessórias das empresas em declaração única digital', NULL, NULL, NULL, NULL, 'rejeitada', 1, NOW()),
(29, 1, 11, 45, 'O plano propõe ampliar pontos públicos de internet gratuita em áreas rurais.', 'Ampliar pontos públicos de internet gratuita em áreas rurais', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(30, 1, 11, 46, 'O plano propõe recompor o orçamento das agências federais de fomento à pesquisa.', 'Recompor o orçamento das agências federais de fomento à pesquisa', NULL, 'Primeiro ano de mandato', NULL, 'Fundo Nacional de Desenvolvimento Científico e Tecnológico', 'publicada', 1, NOW()),
(31, 1, 11, 48, 'O plano propõe fortalecer a autoridade nacional de proteção de dados com ampliação do quadro técnico.', 'Fortalecer a autoridade nacional de proteção de dados com ampliação do quadro técnico', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(32, 1, 12, 51, 'O plano propõe implantar avaliação de desempenho baseada em metas para órgãos federais.', 'Implantar avaliação de desempenho baseada em metas para órgãos federais', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(33, 1, 13, 53, 'O plano propõe abrir escritórios de promoção comercial em mercados emergentes.', 'Abrir escritórios de promoção comercial em mercados emergentes', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(34, 1, 13, 55, 'O plano propõe ampliar a rede de atendimento consular a brasileiros no exterior.', 'Ampliar a rede de atendimento consular a brasileiros no exterior', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(35, 1, 14, 57, 'O plano propõe ampliar a oferta de gás natural para a indústria.', 'Ampliar a oferta de gás natural para a indústria', NULL, NULL, NULL, NULL, 'em_revisao', NULL, NULL),
(36, 2, 1, 1, 'O plano propõe ampliar o horário de funcionamento das unidades básicas de saúde para o período noturno.', 'Ampliar o horário de funcionamento das unidades básicas de saúde para o período noturno', 'Unidades básicas com atendimento até 22h nas cidades acima de 100 mil habitantes', 'Até o fim do segundo ano de mandato', 'R$ 4,2 bilhões por ano', 'Orçamento federal da saúde', 'publicada', 1, NOW()),
(37, 2, 1, 2, 'O plano propõe financiar a reforma e o reequipamento de hospitais filantrópicos.', 'Financiar a reforma e o reequipamento de hospitais filantrópicos', NULL, NULL, 'R$ 2,5 bilhões', NULL, 'publicada', 1, NOW()),
(38, 2, 1, 4, 'O plano propõe estimular a produção nacional de insumos farmacêuticos por laboratórios públicos.', 'Estimular a produção nacional de insumos farmacêuticos por laboratórios públicos', NULL, 'Oito anos', NULL, 'Recursos do complexo econômico-industrial da saúde', 'publicada', 1, NOW()),
(39, 2, 2, 6, 'O plano propõe criar bolsa de apoio para famílias em fila de espera por vaga em creche.', 'Criar bolsa de apoio para famílias em fila de espera por vaga em creche', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(40, 2, 2, 8, 'O plano propõe pagar poupança de permanência a estudantes de baixa renda do ensino médio.', 'Pagar poupança de permanência a estudantes de baixa renda do ensino médio', NULL, 'Primeiro ano de mandato', 'R$ 6 bilhões por ano', 'Fundo específico com recursos do pré-sal', 'publicada', 1, NOW()),
(41, 2, 2, 9, 'O plano propõe recompor o orçamento de custeio das universidades federais.', 'Recompor o orçamento de custeio das universidades federais', NULL, 'Dois anos', NULL, 'Orçamento do Ministério da Educação', 'rejeitada', 1, NOW()),
(42, 2, 2, 10, 'O plano propõe estabelecer plano de valorização salarial vinculado ao piso nacional do magistério.', 'Estabelecer plano de valorização salarial vinculado ao piso nacional do magistério', NULL, 'Quatro anos', NULL, 'Complementação da União ao Fundeb', 'publicada', 1, NOW()),
(43, 2, 3, 12, 'O plano propõe revisar benefícios e renúncias fiscais concedidos a setores específicos.', 'Revisar benefícios e renúncias fiscais concedidos a setores específicos', NULL, 'Quatro anos', NULL, NULL, 'publicada', 1, NOW()),
(44, 2, 3, 13, 'O plano propõe criar programa de investimentos em parceria com o setor privado.', 'Criar programa de investimentos em parceria com o setor privado', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(45, 2, 4, 15, 'O plano propõe reduzir encargos sobre a folha de pagamento para o primeiro emprego de jovens.', 'Reduzir encargos sobre a folha de pagamento para o primeiro emprego de jovens', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(46, 2, 4, 16, 'O plano propõe oferecer cursos de qualificação profissional conectados à demanda regional.', 'Oferecer cursos de qualificação profissional conectados à demanda regional', '2 milhões de pessoas qualificadas', 'Quatro anos', NULL, NULL, 'publicada', 1, NOW()),
(47, 2, 4, 17, 'O plano propõe criar linha de microcrédito orientado para empreendedores de baixa renda.', 'Criar linha de microcrédito orientado para empreendedores de baixa renda', NULL, NULL, NULL, NULL, 'em_revisao', NULL, NULL),
(48, 2, 4, 18, 'O plano propõe manter política de valorização do salário mínimo com regra de reajuste definida em lei.', 'Manter política de valorização do salário mínimo com regra de reajuste definida em lei', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(49, 2, 5, 23, 'O plano propõe ampliar a rede de Casas da Mulher Brasileira.', 'Ampliar a rede de Casas da Mulher Brasileira', NULL, 'Quatro anos', NULL, NULL, 'publicada', 1, NOW()),
(50, 2, 6, 24, 'O plano propõe priorizar reforma de imóveis vazios em áreas centrais para habitação social.', 'Priorizar reforma de imóveis vazios em áreas centrais para habitação social', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(51, 2, 6, 25, 'O plano propõe entregar títulos de propriedade a famílias em áreas urbanas ocupadas.', 'Entregar títulos de propriedade a famílias em áreas urbanas ocupadas', '1 milhão de títulos emitidos', NULL, NULL, NULL, 'publicada', 1, NOW()),
(52, 2, 7, 29, 'O plano propõe estudar a retomada do transporte ferroviário de passageiros entre capitais.', 'Estudar a retomada do transporte ferroviário de passageiros entre capitais', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(53, 2, 7, 31, 'O plano propõe criar subsídio federal para reduzir a tarifa do transporte coletivo urbano.', 'Criar subsídio federal para reduzir a tarifa do transporte coletivo urbano', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(54, 2, 8, 35, 'O plano propõe criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga.', 'Criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga', '12 milhões de hectares recuperados', 'Até 2030', NULL, NULL, 'publicada', 1, NOW()),
(55, 2, 8, 36, 'O plano propõe apoiar municípios no encerramento de lixões e na coleta seletiva.', 'Apoiar municípios no encerramento de lixões e na coleta seletiva', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(56, 2, 9, 38, 'O plano propõe acompanhar a implantação do novo imposto sobre valor agregado com revisão periódica de alíquotas.', 'Acompanhar a implantação do novo imposto sobre valor agregado com revisão periódica de alíquotas', NULL, NULL, NULL, NULL, 'pendente', NULL, NULL),
(57, 2, 9, 39, 'O plano propõe corrigir anualmente a tabela do imposto de renda pela inflação.', 'Corrigir anualmente a tabela do imposto de renda pela inflação', NULL, NULL, NULL, NULL, 'pendente', NULL, NULL),
(58, 2, 9, 40, 'O plano propõe unificar obrigações acessórias das empresas em declaração única digital.', 'Unificar obrigações acessórias das empresas em declaração única digital', NULL, 'Três anos', NULL, NULL, 'publicada', 1, NOW()),
(59, 2, 10, 41, 'O plano propõe criar regra de saída gradual do programa de transferência para quem conseguir emprego formal.', 'Criar regra de saída gradual do programa de transferência para quem conseguir emprego formal', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(60, 2, 10, 44, 'O plano propõe reduzir o tempo de análise de pedidos do benefício de prestação continuada.', 'Reduzir o tempo de análise de pedidos do benefício de prestação continuada', 'Análise concluída em até 45 dias', 'Dois anos', NULL, NULL, 'publicada', 1, NOW()),
(61, 2, 12, 50, 'O plano propõe fortalecer os órgãos de controle interno com autonomia técnica.', 'Fortalecer os órgãos de controle interno com autonomia técnica', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(62, 2, 12, 52, 'O plano propõe rever critérios de distribuição de recursos federais para estados e municípios.', 'Rever critérios de distribuição de recursos federais para estados e municípios', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(63, 2, 13, 53, 'O plano propõe concluir acordos comerciais em negociação com outros blocos econômicos.', 'Concluir acordos comerciais em negociação com outros blocos econômicos', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(64, 2, 14, 56, 'O plano propõe financiar geração solar em residências de baixa renda.', 'Financiar geração solar em residências de baixa renda', '500 mil residências com geração solar', NULL, NULL, 'Recursos do setor elétrico', 'pendente', NULL, NULL),
(65, 2, 14, 58, 'O plano propõe revisar encargos embutidos na conta de luz para reduzir a tarifa residencial.', 'Revisar encargos embutidos na conta de luz para reduzir a tarifa residencial', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(66, 2, 14, 59, 'O plano propõe criar programa nacional de hidrogênio de baixo carbono.', 'Criar programa nacional de hidrogênio de baixo carbono', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(67, 3, 1, 3, 'O plano propõe incluir atendimento psicológico nas unidades básicas de saúde.', 'Incluir atendimento psicológico nas unidades básicas de saúde', NULL, 'Até 2028', NULL, 'Orçamento federal da saúde', 'publicada', 1, NOW()),
(68, 3, 1, 4, 'O plano propõe ampliar a lista de medicamentos distribuídos gratuitamente pelo programa de farmácia popular.', 'Ampliar a lista de medicamentos distribuídos gratuitamente pelo programa de farmácia popular', 'Inclusão de 40 novos medicamentos', NULL, NULL, NULL, 'rejeitada', 1, NOW()),
(69, 3, 3, 11, 'O plano propõe rever a composição do conselho que define a meta de inflação.', 'Rever a composição do conselho que define a meta de inflação', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(70, 3, 3, 12, 'O plano propõe adotar meta de resultado primário crescente ao longo do mandato.', 'Adotar meta de resultado primário crescente ao longo do mandato', 'Superávit primário de 1% do PIB', 'Até o quarto ano', NULL, NULL, 'publicada', 1, NOW()),
(71, 3, 4, 18, 'O plano propõe ampliar o seguro-desemprego para trabalhadores por aplicativo.', 'Ampliar o seguro-desemprego para trabalhadores por aplicativo', NULL, NULL, NULL, 'Fundo de Amparo ao Trabalhador', 'publicada', 1, NOW()),
(72, 3, 5, 20, 'O plano propõe criar força-tarefa nacional permanente de investigação financeira do crime organizado.', 'Criar força-tarefa nacional permanente de investigação financeira do crime organizado', NULL, 'Primeiro ano de mandato', NULL, NULL, 'publicada', 1, NOW()),
(73, 3, 6, 25, 'O plano propõe entregar títulos de propriedade a famílias em áreas urbanas ocupadas.', 'Entregar títulos de propriedade a famílias em áreas urbanas ocupadas', '1 milhão de títulos emitidos', NULL, NULL, NULL, 'publicada', 1, NOW()),
(74, 3, 6, 26, 'O plano propõe criar programa federal de aluguel social com participação de estados e municípios.', 'Criar programa federal de aluguel social com participação de estados e municípios', NULL, NULL, NULL, 'Cofinanciamento federativo', 'publicada', 1, NOW()),
(75, 3, 6, 27, 'O plano propõe urbanizar comunidades com obras de drenagem, iluminação e pavimentação.', 'Urbanizar comunidades com obras de drenagem, iluminação e pavimentação', NULL, 'Oito anos', NULL, 'Programa de Aceleração de Investimentos', 'publicada', 1, NOW()),
(76, 3, 7, 30, 'O plano propõe acelerar a universalização do acesso a água tratada e coleta de esgoto.', 'Acelerar a universalização do acesso a água tratada e coleta de esgoto', NULL, 'Até 2033', NULL, 'Concessões e financiamento de bancos públicos', 'pendente', NULL, NULL),
(77, 3, 7, 31, 'O plano propõe financiar corredores de ônibus e sistemas sobre trilhos em regiões metropolitanas.', 'Financiar corredores de ônibus e sistemas sobre trilhos em regiões metropolitanas', NULL, NULL, 'R$ 20 bilhões', NULL, 'publicada', 1, NOW()),
(78, 3, 7, 32, 'O plano propõe ampliar a aviação regional com investimentos em aeroportos de pequeno porte.', 'Ampliar a aviação regional com investimentos em aeroportos de pequeno porte', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(79, 3, 8, 34, 'O plano propõe criar mercado regulado de carbono com participação de setores industriais.', 'Criar mercado regulado de carbono com participação de setores industriais', NULL, 'Dois anos', NULL, NULL, 'publicada', 1, NOW()),
(80, 3, 8, 35, 'O plano propõe criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga.', 'Criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga', NULL, 'Até 2030', NULL, 'Fundos climáticos e cooperação internacional', 'publicada', 1, NOW()),
(81, 3, 8, 37, 'O plano propõe concluir obras de segurança hídrica no Semiárido.', 'Concluir obras de segurança hídrica no Semiárido', NULL, 'Quatro anos', 'R$ 8 bilhões', 'Orçamento federal', 'publicada', 1, NOW()),
(82, 3, 9, 38, 'O plano propõe propor redução gradual da carga tributária sobre o consumo.', 'Propor redução gradual da carga tributária sobre o consumo', NULL, 'Oito anos', NULL, NULL, 'publicada', 1, NOW()),
(83, 3, 9, 39, 'O plano propõe ampliar a faixa de isenção do imposto de renda da pessoa física.', 'Ampliar a faixa de isenção do imposto de renda da pessoa física', NULL, 'Dois anos', NULL, 'Compensação por tributação de rendimentos hoje isentos', 'publicada', 1, NOW()),
(84, 3, 10, 42, 'O plano propõe implantar cozinhas comunitárias em áreas de insegurança alimentar grave.', 'Implantar cozinhas comunitárias em áreas de insegurança alimentar grave', '1.000 cozinhas comunitárias', NULL, NULL, NULL, 'publicada', 1, NOW()),
(85, 3, 10, 44, 'O plano propõe reduzir o tempo de análise de pedidos do benefício de prestação continuada.', 'Reduzir o tempo de análise de pedidos do benefício de prestação continuada', 'Análise concluída em até 45 dias', 'Dois anos', NULL, NULL, 'publicada', 1, NOW()),
(86, 3, 11, 45, 'O plano propõe ampliar pontos públicos de internet gratuita em áreas rurais.', 'Ampliar pontos públicos de internet gratuita em áreas rurais', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(87, 3, 11, 46, 'O plano propõe recompor o orçamento das agências federais de fomento à pesquisa.', 'Recompor o orçamento das agências federais de fomento à pesquisa', NULL, 'Primeiro ano de mandato', NULL, NULL, 'publicada', 1, NOW()),
(88, 3, 12, 51, 'O plano propõe implantar avaliação de desempenho baseada em metas para órgãos federais.', 'Implantar avaliação de desempenho baseada em metas para órgãos federais', NULL, 'Dois anos', NULL, NULL, 'publicada', 1, NOW()),
(89, 3, 13, 53, 'O plano propõe abrir escritórios de promoção comercial em mercados emergentes.', 'Abrir escritórios de promoção comercial em mercados emergentes', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(90, 3, 13, 54, 'O plano propõe fortalecer a integração de infraestrutura com países da América do Sul.', 'Fortalecer a integração de infraestrutura com países da América do Sul', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(91, 3, 14, 56, 'O plano propõe ampliar leilões de energia solar e eólica.', 'Ampliar leilões de energia solar e eólica', NULL, NULL, NULL, NULL, 'aprovada', 1, NOW()),
(92, 3, 14, 57, 'O plano propõe ampliar a oferta de gás natural para a indústria.', 'Ampliar a oferta de gás natural para a indústria', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(93, 3, 14, 59, 'O plano propõe criar programa nacional de hidrogênio de baixo carbono.', 'Criar programa nacional de hidrogênio de baixo carbono', NULL, 'Dez anos', NULL, 'Parcerias com setor privado', 'publicada', 1, NOW()),
(94, 4, 1, 1, 'O plano propõe ampliar o horário de funcionamento das unidades básicas de saúde para o período noturno.', 'Ampliar o horário de funcionamento das unidades básicas de saúde para o período noturno', 'Unidades básicas com atendimento até 22h nas cidades acima de 100 mil habitantes', NULL, 'R$ 4,2 bilhões por ano', NULL, 'publicada', 1, NOW()),
(95, 4, 1, 2, 'O plano propõe financiar a reforma e o reequipamento de hospitais filantrópicos.', 'Financiar a reforma e o reequipamento de hospitais filantrópicos', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(96, 4, 1, 3, 'O plano propõe expandir a rede de Centros de Atenção Psicossocial (CAPS).', 'Expandir a rede de Centros de Atenção Psicossocial (CAPS)', 'Um CAPS para cada 70 mil habitantes', NULL, NULL, NULL, 'publicada', 1, NOW()),
(97, 4, 1, 5, 'O plano propõe criar um sistema nacional unificado de regulação de consultas, exames e cirurgias.', 'Criar um sistema nacional unificado de regulação de consultas, exames e cirurgias', NULL, 'Três anos', NULL, NULL, 'publicada', 1, NOW()),
(98, 4, 2, 8, 'O plano propõe pagar poupança de permanência a estudantes de baixa renda do ensino médio.', 'Pagar poupança de permanência a estudantes de baixa renda do ensino médio', NULL, 'Primeiro ano de mandato', 'R$ 6 bilhões por ano', NULL, 'publicada', 1, NOW()),
(99, 4, 3, 11, 'O plano propõe manter o regime de metas de inflação com autonomia operacional do Banco Central.', 'Manter o regime de metas de inflação com autonomia operacional do Banco Central', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(100, 4, 3, 12, 'O plano propõe revisar benefícios e renúncias fiscais concedidos a setores específicos.', 'Revisar benefícios e renúncias fiscais concedidos a setores específicos', 'Redução de 20% nas renúncias fiscais', 'Quatro anos', NULL, NULL, 'publicada', 1, NOW()),
(101, 4, 3, 14, 'O plano propõe simplificar procedimentos de exportação para pequenas empresas.', 'Simplificar procedimentos de exportação para pequenas empresas', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(102, 4, 4, 16, 'O plano propõe oferecer cursos de qualificação profissional conectados à demanda regional.', 'Oferecer cursos de qualificação profissional conectados à demanda regional', '2 milhões de pessoas qualificadas', 'Quatro anos', NULL, NULL, 'publicada', 1, NOW()),
(103, 4, 4, 18, 'O plano propõe manter política de valorização do salário mínimo com regra de reajuste definida em lei.', 'Manter política de valorização do salário mínimo com regra de reajuste definida em lei', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(104, 4, 7, 30, 'O plano propõe acelerar a universalização do acesso a água tratada e coleta de esgoto.', 'Acelerar a universalização do acesso a água tratada e coleta de esgoto', '99% com água tratada e 90% com coleta de esgoto', NULL, NULL, NULL, 'publicada', 1, NOW()),
(105, 4, 7, 31, 'O plano propõe criar subsídio federal para reduzir a tarifa do transporte coletivo urbano.', 'Criar subsídio federal para reduzir a tarifa do transporte coletivo urbano', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(106, 4, 8, 33, 'O plano propõe ampliar o monitoramento por satélite em todos os biomas.', 'Ampliar o monitoramento por satélite em todos os biomas', NULL, NULL, NULL, NULL, 'pendente', NULL, NULL),
(107, 4, 8, 34, 'O plano propõe atualizar as metas nacionais de redução de emissões de gases de efeito estufa.', 'Atualizar as metas nacionais de redução de emissões de gases de efeito estufa', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(108, 4, 8, 35, 'O plano propõe criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga.', 'Criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga', NULL, NULL, NULL, 'Fundos climáticos e cooperação internacional', 'publicada', 1, NOW()),
(109, 4, 8, 37, 'O plano propõe concluir obras de segurança hídrica no Semiárido.', 'Concluir obras de segurança hídrica no Semiárido', NULL, 'Quatro anos', 'R$ 8 bilhões', NULL, 'em_revisao', NULL, NULL),
(110, 4, 9, 40, 'O plano propõe unificar obrigações acessórias das empresas em declaração única digital.', 'Unificar obrigações acessórias das empresas em declaração única digital', NULL, 'Três anos', NULL, NULL, 'publicada', 1, NOW()),
(111, 4, 11, 45, 'O plano propõe levar internet de alta velocidade a todas as escolas públicas.', 'Levar internet de alta velocidade a todas as escolas públicas', '100% das escolas conectadas', NULL, NULL, 'Fundo de Universalização dos Serviços de Telecomunicações', 'publicada', 1, NOW()),
(112, 4, 11, 46, 'O plano propõe criar programa de apoio a startups de base tecnológica em universidades.', 'Criar programa de apoio a startups de base tecnológica em universidades', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(113, 4, 12, 49, 'O plano propõe publicar em formato aberto todos os dados de execução orçamentária em tempo real.', 'Publicar em formato aberto todos os dados de execução orçamentária em tempo real', NULL, 'Primeiro ano de mandato', NULL, NULL, 'publicada', 1, NOW()),
(114, 4, 12, 52, 'O plano propõe rever critérios de distribuição de recursos federais para estados e municípios.', 'Rever critérios de distribuição de recursos federais para estados e municípios', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(115, 4, 13, 54, 'O plano propõe fortalecer a integração de infraestrutura com países da América do Sul.', 'Fortalecer a integração de infraestrutura com países da América do Sul', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(116, 4, 14, 56, 'O plano propõe financiar geração solar em residências de baixa renda.', 'Financiar geração solar em residências de baixa renda', '500 mil residências com geração solar', NULL, NULL, 'Recursos do setor elétrico', 'publicada', 1, NOW()),
(117, 4, 14, 57, 'O plano propõe rever a política de preços de combustíveis praticada pela estatal de petróleo.', 'Rever a política de preços de combustíveis praticada pela estatal de petróleo', NULL, NULL, NULL, NULL, 'publicada', 1, NOW()),
(118, 4, 14, 58, 'O plano propõe revisar encargos embutidos na conta de luz para reduzir a tarifa residencial.', 'Revisar encargos embutidos na conta de luz para reduzir a tarifa residencial', NULL, 'Dois anos', NULL, NULL, 'publicada', 1, NOW());

INSERT INTO fontes (id, proposta_id, documento_id, pagina, localizacao, trecho_original) VALUES
(1, 1, 1, 7, 'Eixo 01 — Saúde', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SAÚDE, o programa prevê contratar agentes comunitários de saúde para ampliar a cobertura da Estratégia Saúde da Família. A meta estabelecida é: cobertura de 85% da população pela Saúde da Família. Os recursos virão de: repasses federais aos municípios. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(2, 2, 1, 7, 'Eixo 01 — Saúde', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SAÚDE, o programa prevê realizar mutirões nacionais de cirurgias eletivas em parceria com estados. O prazo previsto é: primeiro ano de mandato. O custo estimado apresentado é de R$ 800 milhões. Os recursos virão de: fundo Nacional de Saúde. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(3, 3, 1, 10, 'Eixo 02 — Educação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EDUCAÇÃO, o programa prevê construir e equipar novas creches em parceria com os municípios. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(4, 4, 1, 10, 'Eixo 02 — Educação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EDUCAÇÃO, o programa prevê integrar cursos técnicos ao ensino médio nas redes estaduais. A meta estabelecida é: 1 milhão de matrículas em ensino médio técnico. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(5, 5, 1, 10, 'Eixo 02 — Educação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EDUCAÇÃO, o programa prevê ampliar vagas em cursos de graduação noturnos. A meta estabelecida é: 200 mil novas vagas noturnas. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(6, 6, 1, 10, 'Eixo 02 — Educação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EDUCAÇÃO, o programa prevê criar programa nacional de formação continuada para professores da educação básica. A meta estabelecida é: 500 mil professores formados. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(7, 7, 1, 13, 'Eixo 03 — Economia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ECONOMIA, o programa prevê rever a composição do conselho que define a meta de inflação. O prazo previsto é: primeiro ano de mandato. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(8, 8, 1, 13, 'Eixo 03 — Economia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ECONOMIA, o programa prevê adotar meta de resultado primário crescente ao longo do mandato. A meta estabelecida é: superávit primário de 1% do PIB. O prazo previsto é: até o quarto ano. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(9, 9, 1, 13, 'Eixo 03 — Economia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ECONOMIA, o programa prevê reduzir o custo do crédito para micro e pequenas empresas por meio de bancos públicos. Os recursos virão de: fundos garantidores. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(10, 10, 1, 13, 'Eixo 03 — Economia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ECONOMIA, o programa prevê lançar política de modernização do parque industrial com foco em produtividade. O prazo previsto é: dez anos. Os recursos virão de: BNDES e fundos setoriais. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(11, 11, 1, 14, 'Eixo 04 — Emprego e renda', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EMPREGO E RENDA, o programa prevê criar frentes de trabalho em obras de infraestrutura local. A meta estabelecida é: 500 mil vagas temporárias. O prazo previsto é: dois anos. O custo estimado apresentado é de R$ 12 bilhões. Os recursos virão de: orçamento federal de investimentos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(12, 12, 1, 14, 'Eixo 04 — Emprego e renda', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EMPREGO E RENDA, o programa prevê ampliar o limite de faturamento do microempreendedor individual (MEI). Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(13, 13, 1, 14, 'Eixo 04 — Emprego e renda', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EMPREGO E RENDA, o programa prevê ampliar o seguro-desemprego para trabalhadores por aplicativo. Os recursos virão de: fundo de Amparo ao Trabalhador. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(14, 14, 1, 15, 'Eixo 05 — Segurança pública', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SEGURANÇA PÚBLICA, o programa prevê ampliar programas de trabalho e estudo para pessoas privadas de liberdade. A meta estabelecida é: 50% da população prisional em trabalho ou estudo. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(15, 15, 1, 15, 'Eixo 05 — Segurança pública', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SEGURANÇA PÚBLICA, o programa prevê ampliar o monitoramento das fronteiras terrestres com radares e drones. Os recursos virão de: orçamento da defesa. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(16, 16, 1, 15, 'Eixo 05 — Segurança pública', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SEGURANÇA PÚBLICA, o programa prevê garantir atendimento 24 horas em delegacias especializadas de atendimento à mulher. Os recursos virão de: fundo Nacional de Segurança Pública. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(17, 17, 1, 18, 'Eixo 06 — Habitação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo HABITAÇÃO, o programa prevê criar programa federal de aluguel social com participação de estados e municípios. Os recursos virão de: cofinanciamento federativo. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(18, 18, 1, 18, 'Eixo 06 — Habitação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo HABITAÇÃO, o programa prevê urbanizar comunidades com obras de drenagem, iluminação e pavimentação. O prazo previsto é: oito anos. Os recursos virão de: programa de Aceleração de Investimentos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(19, 19, 1, 20, 'Eixo 07 — Infraestrutura', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo INFRAESTRUTURA, o programa prevê recuperar trechos de rodovias federais classificados como ruins ou péssimos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(20, 20, 1, 20, 'Eixo 07 — Infraestrutura', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo INFRAESTRUTURA, o programa prevê concluir obras ferroviárias de integração entre regiões produtoras e portos. O prazo previsto é: oito anos. Os recursos virão de: parcerias público-privadas. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(21, 21, 1, 20, 'Eixo 07 — Infraestrutura', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo INFRAESTRUTURA, o programa prevê financiar corredores de ônibus e sistemas sobre trilhos em regiões metropolitanas. O custo estimado apresentado é de R$ 20 bilhões. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(22, 22, 1, 22, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê zerar o desmatamento ilegal na Amazônia com ampliação da fiscalização. A meta estabelecida é: desmatamento ilegal zero. O prazo previsto é: até 2030. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(23, 23, 1, 22, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê criar mercado regulado de carbono com participação de setores industriais. O prazo previsto é: dois anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(24, 24, 1, 22, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga. A meta estabelecida é: 12 milhões de hectares recuperados. O prazo previsto é: até 2030. Os recursos virão de: fundos climáticos e cooperação internacional. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(25, 25, 1, 22, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê criar incentivo a cooperativas de catadores de materiais recicláveis. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(26, 26, 1, 22, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê concluir obras de segurança hídrica no Semiárido. O prazo previsto é: quatro anos. Os recursos virão de: orçamento federal. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(27, 27, 1, 24, 'Eixo 09 — Impostos', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo IMPOSTOS, o programa prevê ampliar a faixa de isenção do imposto de renda da pessoa física. O prazo previsto é: dois anos. Os recursos virão de: compensação por tributação de rendimentos hoje isentos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(28, 28, 1, 24, 'Eixo 09 — Impostos', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo IMPOSTOS, o programa prevê unificar obrigações acessórias das empresas em declaração única digital. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(29, 29, 1, 26, 'Eixo 11 — Tecnologia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo TECNOLOGIA, o programa prevê ampliar pontos públicos de internet gratuita em áreas rurais. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(30, 30, 1, 26, 'Eixo 11 — Tecnologia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo TECNOLOGIA, o programa prevê recompor o orçamento das agências federais de fomento à pesquisa. O prazo previsto é: primeiro ano de mandato. Os recursos virão de: fundo Nacional de Desenvolvimento Científico e Tecnológico. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(31, 31, 1, 26, 'Eixo 11 — Tecnologia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo TECNOLOGIA, o programa prevê fortalecer a autoridade nacional de proteção de dados com ampliação do quadro técnico. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(32, 32, 1, 27, 'Eixo 12 — Gestão pública', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo GESTÃO PÚBLICA, o programa prevê implantar avaliação de desempenho baseada em metas para órgãos federais. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(33, 33, 1, 30, 'Eixo 13 — Relações exteriores', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo RELAÇÕES EXTERIORES, o programa prevê abrir escritórios de promoção comercial em mercados emergentes. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(34, 34, 1, 30, 'Eixo 13 — Relações exteriores', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo RELAÇÕES EXTERIORES, o programa prevê ampliar a rede de atendimento consular a brasileiros no exterior. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(35, 35, 1, 31, 'Eixo 14 — Energia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ENERGIA, o programa prevê ampliar a oferta de gás natural para a indústria. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(36, 36, 2, 7, 'Eixo 01 — Saúde', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SAÚDE, o programa prevê ampliar o horário de funcionamento das unidades básicas de saúde para o período noturno. A meta estabelecida é: unidades básicas com atendimento até 22h nas cidades acima de 100 mil habitantes. O prazo previsto é: até o fim do segundo ano de mandato. O custo estimado apresentado é de R$ 4,2 bilhões por ano. Os recursos virão de: orçamento federal da saúde. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(37, 37, 2, 7, 'Eixo 01 — Saúde', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SAÚDE, o programa prevê financiar a reforma e o reequipamento de hospitais filantrópicos. O custo estimado apresentado é de R$ 2,5 bilhões. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(38, 38, 2, 7, 'Eixo 01 — Saúde', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SAÚDE, o programa prevê estimular a produção nacional de insumos farmacêuticos por laboratórios públicos. O prazo previsto é: oito anos. Os recursos virão de: recursos do complexo econômico-industrial da saúde. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(39, 39, 2, 10, 'Eixo 02 — Educação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EDUCAÇÃO, o programa prevê criar bolsa de apoio para famílias em fila de espera por vaga em creche. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(40, 40, 2, 10, 'Eixo 02 — Educação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EDUCAÇÃO, o programa prevê pagar poupança de permanência a estudantes de baixa renda do ensino médio. O prazo previsto é: primeiro ano de mandato. O custo estimado apresentado é de R$ 6 bilhões por ano. Os recursos virão de: fundo específico com recursos do pré-sal. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(41, 41, 2, 10, 'Eixo 02 — Educação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EDUCAÇÃO, o programa prevê recompor o orçamento de custeio das universidades federais. O prazo previsto é: dois anos. Os recursos virão de: orçamento do Ministério da Educação. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(42, 42, 2, 10, 'Eixo 02 — Educação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EDUCAÇÃO, o programa prevê estabelecer plano de valorização salarial vinculado ao piso nacional do magistério. O prazo previsto é: quatro anos. Os recursos virão de: complementação da União ao Fundeb. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(43, 43, 2, 11, 'Eixo 03 — Economia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ECONOMIA, o programa prevê revisar benefícios e renúncias fiscais concedidos a setores específicos. O prazo previsto é: quatro anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(44, 44, 2, 11, 'Eixo 03 — Economia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ECONOMIA, o programa prevê criar programa de investimentos em parceria com o setor privado. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(45, 45, 2, 13, 'Eixo 04 — Emprego e renda', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EMPREGO E RENDA, o programa prevê reduzir encargos sobre a folha de pagamento para o primeiro emprego de jovens. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(46, 46, 2, 13, 'Eixo 04 — Emprego e renda', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EMPREGO E RENDA, o programa prevê oferecer cursos de qualificação profissional conectados à demanda regional. A meta estabelecida é: 2 milhões de pessoas qualificadas. O prazo previsto é: quatro anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(47, 47, 2, 13, 'Eixo 04 — Emprego e renda', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EMPREGO E RENDA, o programa prevê criar linha de microcrédito orientado para empreendedores de baixa renda. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(48, 48, 2, 13, 'Eixo 04 — Emprego e renda', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EMPREGO E RENDA, o programa prevê manter política de valorização do salário mínimo com regra de reajuste definida em lei. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(49, 49, 2, 16, 'Eixo 05 — Segurança pública', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SEGURANÇA PÚBLICA, o programa prevê ampliar a rede de Casas da Mulher Brasileira. O prazo previsto é: quatro anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(50, 50, 2, 19, 'Eixo 06 — Habitação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo HABITAÇÃO, o programa prevê priorizar reforma de imóveis vazios em áreas centrais para habitação social. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(51, 51, 2, 19, 'Eixo 06 — Habitação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo HABITAÇÃO, o programa prevê entregar títulos de propriedade a famílias em áreas urbanas ocupadas. A meta estabelecida é: 1 milhão de títulos emitidos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(52, 52, 2, 22, 'Eixo 07 — Infraestrutura', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo INFRAESTRUTURA, o programa prevê estudar a retomada do transporte ferroviário de passageiros entre capitais. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(53, 53, 2, 22, 'Eixo 07 — Infraestrutura', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo INFRAESTRUTURA, o programa prevê criar subsídio federal para reduzir a tarifa do transporte coletivo urbano. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(54, 54, 2, 25, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga. A meta estabelecida é: 12 milhões de hectares recuperados. O prazo previsto é: até 2030. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(55, 55, 2, 25, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê apoiar municípios no encerramento de lixões e na coleta seletiva. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(56, 56, 2, 27, 'Eixo 09 — Impostos', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo IMPOSTOS, o programa prevê acompanhar a implantação do novo imposto sobre valor agregado com revisão periódica de alíquotas. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(57, 57, 2, 27, 'Eixo 09 — Impostos', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo IMPOSTOS, o programa prevê corrigir anualmente a tabela do imposto de renda pela inflação. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(58, 58, 2, 27, 'Eixo 09 — Impostos', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo IMPOSTOS, o programa prevê unificar obrigações acessórias das empresas em declaração única digital. O prazo previsto é: três anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(59, 59, 2, 29, 'Eixo 10 — Assistência social', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ASSISTÊNCIA SOCIAL, o programa prevê criar regra de saída gradual do programa de transferência para quem conseguir emprego formal. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(60, 60, 2, 29, 'Eixo 10 — Assistência social', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ASSISTÊNCIA SOCIAL, o programa prevê reduzir o tempo de análise de pedidos do benefício de prestação continuada. A meta estabelecida é: análise concluída em até 45 dias. O prazo previsto é: dois anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(61, 61, 2, 34, 'Eixo 12 — Gestão pública', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo GESTÃO PÚBLICA, o programa prevê fortalecer os órgãos de controle interno com autonomia técnica. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(62, 62, 2, 34, 'Eixo 12 — Gestão pública', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo GESTÃO PÚBLICA, o programa prevê rever critérios de distribuição de recursos federais para estados e municípios. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(63, 63, 2, 35, 'Eixo 13 — Relações exteriores', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo RELAÇÕES EXTERIORES, o programa prevê concluir acordos comerciais em negociação com outros blocos econômicos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(64, 64, 2, 36, 'Eixo 14 — Energia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ENERGIA, o programa prevê financiar geração solar em residências de baixa renda. A meta estabelecida é: 500 mil residências com geração solar. Os recursos virão de: recursos do setor elétrico. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(65, 65, 2, 36, 'Eixo 14 — Energia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ENERGIA, o programa prevê revisar encargos embutidos na conta de luz para reduzir a tarifa residencial. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(66, 66, 2, 36, 'Eixo 14 — Energia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ENERGIA, o programa prevê criar programa nacional de hidrogênio de baixo carbono. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(67, 67, 3, 9, 'Eixo 01 — Saúde', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SAÚDE, o programa prevê incluir atendimento psicológico nas unidades básicas de saúde. O prazo previsto é: até 2028. Os recursos virão de: orçamento federal da saúde. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(68, 68, 3, 9, 'Eixo 01 — Saúde', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SAÚDE, o programa prevê ampliar a lista de medicamentos distribuídos gratuitamente pelo programa de farmácia popular. A meta estabelecida é: inclusão de 40 novos medicamentos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(69, 69, 3, 13, 'Eixo 03 — Economia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ECONOMIA, o programa prevê rever a composição do conselho que define a meta de inflação. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(70, 70, 3, 13, 'Eixo 03 — Economia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ECONOMIA, o programa prevê adotar meta de resultado primário crescente ao longo do mandato. A meta estabelecida é: superávit primário de 1% do PIB. O prazo previsto é: até o quarto ano. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(71, 71, 3, 16, 'Eixo 04 — Emprego e renda', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EMPREGO E RENDA, o programa prevê ampliar o seguro-desemprego para trabalhadores por aplicativo. Os recursos virão de: fundo de Amparo ao Trabalhador. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(72, 72, 3, 17, 'Eixo 05 — Segurança pública', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SEGURANÇA PÚBLICA, o programa prevê criar força-tarefa nacional permanente de investigação financeira do crime organizado. O prazo previsto é: primeiro ano de mandato. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(73, 73, 3, 20, 'Eixo 06 — Habitação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo HABITAÇÃO, o programa prevê entregar títulos de propriedade a famílias em áreas urbanas ocupadas. A meta estabelecida é: 1 milhão de títulos emitidos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(74, 74, 3, 20, 'Eixo 06 — Habitação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo HABITAÇÃO, o programa prevê criar programa federal de aluguel social com participação de estados e municípios. Os recursos virão de: cofinanciamento federativo. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(75, 75, 3, 20, 'Eixo 06 — Habitação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo HABITAÇÃO, o programa prevê urbanizar comunidades com obras de drenagem, iluminação e pavimentação. O prazo previsto é: oito anos. Os recursos virão de: programa de Aceleração de Investimentos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(76, 76, 3, 22, 'Eixo 07 — Infraestrutura', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo INFRAESTRUTURA, o programa prevê acelerar a universalização do acesso a água tratada e coleta de esgoto. O prazo previsto é: até 2033. Os recursos virão de: concessões e financiamento de bancos públicos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(77, 77, 3, 22, 'Eixo 07 — Infraestrutura', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo INFRAESTRUTURA, o programa prevê financiar corredores de ônibus e sistemas sobre trilhos em regiões metropolitanas. O custo estimado apresentado é de R$ 20 bilhões. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(78, 78, 3, 22, 'Eixo 07 — Infraestrutura', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo INFRAESTRUTURA, o programa prevê ampliar a aviação regional com investimentos em aeroportos de pequeno porte. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(79, 79, 3, 25, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê criar mercado regulado de carbono com participação de setores industriais. O prazo previsto é: dois anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(80, 80, 3, 25, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga. O prazo previsto é: até 2030. Os recursos virão de: fundos climáticos e cooperação internacional. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(81, 81, 3, 25, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê concluir obras de segurança hídrica no Semiárido. O prazo previsto é: quatro anos. O custo estimado apresentado é de R$ 8 bilhões. Os recursos virão de: orçamento federal. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(82, 82, 3, 28, 'Eixo 09 — Impostos', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo IMPOSTOS, o programa prevê propor redução gradual da carga tributária sobre o consumo. O prazo previsto é: oito anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(83, 83, 3, 28, 'Eixo 09 — Impostos', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo IMPOSTOS, o programa prevê ampliar a faixa de isenção do imposto de renda da pessoa física. O prazo previsto é: dois anos. Os recursos virão de: compensação por tributação de rendimentos hoje isentos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(84, 84, 3, 30, 'Eixo 10 — Assistência social', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ASSISTÊNCIA SOCIAL, o programa prevê implantar cozinhas comunitárias em áreas de insegurança alimentar grave. A meta estabelecida é: 1.000 cozinhas comunitárias. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(85, 85, 3, 30, 'Eixo 10 — Assistência social', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ASSISTÊNCIA SOCIAL, o programa prevê reduzir o tempo de análise de pedidos do benefício de prestação continuada. A meta estabelecida é: análise concluída em até 45 dias. O prazo previsto é: dois anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(86, 86, 3, 33, 'Eixo 11 — Tecnologia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo TECNOLOGIA, o programa prevê ampliar pontos públicos de internet gratuita em áreas rurais. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(87, 87, 3, 33, 'Eixo 11 — Tecnologia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo TECNOLOGIA, o programa prevê recompor o orçamento das agências federais de fomento à pesquisa. O prazo previsto é: primeiro ano de mandato. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(88, 88, 3, 36, 'Eixo 12 — Gestão pública', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo GESTÃO PÚBLICA, o programa prevê implantar avaliação de desempenho baseada em metas para órgãos federais. O prazo previsto é: dois anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(89, 89, 3, 37, 'Eixo 13 — Relações exteriores', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo RELAÇÕES EXTERIORES, o programa prevê abrir escritórios de promoção comercial em mercados emergentes. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(90, 90, 3, 37, 'Eixo 13 — Relações exteriores', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo RELAÇÕES EXTERIORES, o programa prevê fortalecer a integração de infraestrutura com países da América do Sul. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(91, 91, 3, 39, 'Eixo 14 — Energia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ENERGIA, o programa prevê ampliar leilões de energia solar e eólica. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(92, 92, 3, 39, 'Eixo 14 — Energia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ENERGIA, o programa prevê ampliar a oferta de gás natural para a indústria. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(93, 93, 3, 39, 'Eixo 14 — Energia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ENERGIA, o programa prevê criar programa nacional de hidrogênio de baixo carbono. O prazo previsto é: dez anos. Os recursos virão de: parcerias com setor privado. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(94, 94, 4, 8, 'Eixo 01 — Saúde', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SAÚDE, o programa prevê ampliar o horário de funcionamento das unidades básicas de saúde para o período noturno. A meta estabelecida é: unidades básicas com atendimento até 22h nas cidades acima de 100 mil habitantes. O custo estimado apresentado é de R$ 4,2 bilhões por ano. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(95, 95, 4, 8, 'Eixo 01 — Saúde', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SAÚDE, o programa prevê financiar a reforma e o reequipamento de hospitais filantrópicos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(96, 96, 4, 8, 'Eixo 01 — Saúde', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SAÚDE, o programa prevê expandir a rede de Centros de Atenção Psicossocial (CAPS). A meta estabelecida é: um CAPS para cada 70 mil habitantes. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(97, 97, 4, 8, 'Eixo 01 — Saúde', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo SAÚDE, o programa prevê criar um sistema nacional unificado de regulação de consultas, exames e cirurgias. O prazo previsto é: três anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(98, 98, 4, 11, 'Eixo 02 — Educação', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EDUCAÇÃO, o programa prevê pagar poupança de permanência a estudantes de baixa renda do ensino médio. O prazo previsto é: primeiro ano de mandato. O custo estimado apresentado é de R$ 6 bilhões por ano. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(99, 99, 4, 13, 'Eixo 03 — Economia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ECONOMIA, o programa prevê manter o regime de metas de inflação com autonomia operacional do Banco Central. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(100, 100, 4, 13, 'Eixo 03 — Economia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ECONOMIA, o programa prevê revisar benefícios e renúncias fiscais concedidos a setores específicos. A meta estabelecida é: redução de 20% nas renúncias fiscais. O prazo previsto é: quatro anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(101, 101, 4, 13, 'Eixo 03 — Economia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ECONOMIA, o programa prevê simplificar procedimentos de exportação para pequenas empresas. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(102, 102, 4, 16, 'Eixo 04 — Emprego e renda', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EMPREGO E RENDA, o programa prevê oferecer cursos de qualificação profissional conectados à demanda regional. A meta estabelecida é: 2 milhões de pessoas qualificadas. O prazo previsto é: quatro anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(103, 103, 4, 16, 'Eixo 04 — Emprego e renda', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo EMPREGO E RENDA, o programa prevê manter política de valorização do salário mínimo com regra de reajuste definida em lei. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(104, 104, 4, 21, 'Eixo 07 — Infraestrutura', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo INFRAESTRUTURA, o programa prevê acelerar a universalização do acesso a água tratada e coleta de esgoto. A meta estabelecida é: 99% com água tratada e 90% com coleta de esgoto. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(105, 105, 4, 21, 'Eixo 07 — Infraestrutura', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo INFRAESTRUTURA, o programa prevê criar subsídio federal para reduzir a tarifa do transporte coletivo urbano. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(106, 106, 4, 23, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê ampliar o monitoramento por satélite em todos os biomas. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(107, 107, 4, 23, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê atualizar as metas nacionais de redução de emissões de gases de efeito estufa. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(108, 108, 4, 23, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê criar programa de recuperação de áreas degradadas no Cerrado e na Caatinga. Os recursos virão de: fundos climáticos e cooperação internacional. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(109, 109, 4, 23, 'Eixo 08 — Meio ambiente', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo MEIO AMBIENTE, o programa prevê concluir obras de segurança hídrica no Semiárido. O prazo previsto é: quatro anos. O custo estimado apresentado é de R$ 8 bilhões. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(110, 110, 4, 25, 'Eixo 09 — Impostos', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo IMPOSTOS, o programa prevê unificar obrigações acessórias das empresas em declaração única digital. O prazo previsto é: três anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(111, 111, 4, 27, 'Eixo 11 — Tecnologia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo TECNOLOGIA, o programa prevê levar internet de alta velocidade a todas as escolas públicas. A meta estabelecida é: 100% das escolas conectadas. Os recursos virão de: fundo de Universalização dos Serviços de Telecomunicações. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(112, 112, 4, 27, 'Eixo 11 — Tecnologia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo TECNOLOGIA, o programa prevê criar programa de apoio a startups de base tecnológica em universidades. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(113, 113, 4, 29, 'Eixo 12 — Gestão pública', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo GESTÃO PÚBLICA, o programa prevê publicar em formato aberto todos os dados de execução orçamentária em tempo real. O prazo previsto é: primeiro ano de mandato. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(114, 114, 4, 29, 'Eixo 12 — Gestão pública', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo GESTÃO PÚBLICA, o programa prevê rever critérios de distribuição de recursos federais para estados e municípios. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(115, 115, 4, 30, 'Eixo 13 — Relações exteriores', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo RELAÇÕES EXTERIORES, o programa prevê fortalecer a integração de infraestrutura com países da América do Sul. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(116, 116, 4, 32, 'Eixo 14 — Energia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ENERGIA, o programa prevê financiar geração solar em residências de baixa renda. A meta estabelecida é: 500 mil residências com geração solar. Os recursos virão de: recursos do setor elétrico. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(117, 117, 4, 32, 'Eixo 14 — Energia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ENERGIA, o programa prevê rever a política de preços de combustíveis praticada pela estatal de petróleo. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.'),
(118, 118, 4, 32, 'Eixo 14 — Energia', '[TEXTO FICTÍCIO DE DEMONSTRAÇÃO] No eixo ENERGIA, o programa prevê revisar encargos embutidos na conta de luz para reduzir a tarifa residencial. O prazo previsto é: dois anos. Este parágrafo foi escrito apenas para testar a plataforma e não representa proposta de nenhuma candidatura real.');


-- ---------------------------------------------------------------------
-- ÍNDICES FULLTEXT (OPCIONAIS — melhoram a busca)
-- Ficam no final para que, se o servidor não suportar, todo o resto
-- já tenha sido importado. A busca funciona mesmo sem eles.
-- ---------------------------------------------------------------------
ALTER TABLE propostas ADD FULLTEXT KEY ft_propostas (resumo, acao, meta);
ALTER TABLE fontes    ADD FULLTEXT KEY ft_fontes (trecho_original);
