-- phpMyAdmin SQL Dump
-- Schema base + dados de exemplo — Intranet
-- Depois de importar, rode migrate.php para aplicar as migrações
-- mais recentes (colunas de vídeo/slider, galeria, etc.)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `intranet`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(120) NOT NULL,
  `type` enum('comunicado','noticia') NOT NULL,
  `color` varchar(20) DEFAULT '#00897B',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `type`, `color`, `created_at`) VALUES
(1, 'Institucional', 'institucional', 'comunicado', '#00897B', '2026-04-09 11:22:10'),
(2, 'Recursos Humanos', 'recursos-humanos', 'comunicado', '#00796B', '2026-04-09 11:22:10'),
(3, 'Tecnologia', 'tecnologia', 'comunicado', '#004D40', '2026-04-09 11:22:10'),
(4, 'Geral', 'geral', 'noticia', '#009688', '2026-04-09 11:22:10');

-- --------------------------------------------------------

--
-- Estrutura para tabela `modules`
--

CREATE TABLE `modules` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `icon` varchar(100) DEFAULT 'link',
  `icon_image` varchar(255) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#00897B',
  `category` enum('sistema','link_rapido','navbar') DEFAULT 'sistema',
  `target` enum('_blank','_self') DEFAULT '_blank',
  `sort_order` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `is_public` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `modules`
-- (exemplos — edite/apague em Admin → Módulos e configure as URLs reais)
--

INSERT INTO `modules` (`id`, `name`, `description`, `url`, `icon`, `icon_image`, `color`, `category`, `target`, `sort_order`, `active`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'Chamados', 'Sistema de chamados de TI', '#', 'support_agent', NULL, '#1565c0', 'sistema', '_blank', 1, 1, 1, '2026-04-09 11:22:10', '2026-04-09 11:22:10'),
(2, 'Indicadores', 'Sistema de indicadores', '#', 'analytics', NULL, '#6A1B9A', 'sistema', '_blank', 2, 1, 1, '2026-04-09 11:22:10', '2026-04-09 11:22:10'),
(3, 'Documentos', 'Repositório de documentos', '#', 'folder', NULL, '#2E7D32', 'sistema', '_blank', 3, 1, 1, '2026-04-09 11:22:10', '2026-04-09 11:22:10'),
(4, 'E-mail Institucional', 'Webmail', '#', 'email', NULL, '#00897b', 'link_rapido', '_blank', 1, 1, 1, '2026-04-09 11:22:10', '2026-04-09 11:22:10'),
(5, 'Portal Gov.br', 'Serviços Federais', 'https://www.gov.br', 'account_balance', NULL, '#00695C', 'link_rapido', '_blank', 2, 1, 1, '2026-04-09 11:22:10', '2026-04-09 11:22:10');

-- --------------------------------------------------------

--
-- Estrutura para tabela `nav_items`
--

CREATE TABLE `nav_items` (
  `id` int(11) NOT NULL,
  `label` varchar(100) NOT NULL,
  `url` varchar(500) DEFAULT NULL,
  `icon` varchar(80) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `active` tinyint(1) DEFAULT 1,
  `open_new_tab` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `nav_items`
--

INSERT INTO `nav_items` (`id`, `label`, `url`, `icon`, `parent_id`, `sort_order`, `active`, `open_new_tab`) VALUES
(1, 'Início', 'index.php', 'home', NULL, 1, 1, 0),
(2, 'Comunicados', 'index.php?page=comunicados', 'campaign', NULL, 2, 1, 0),
(3, 'Notícias Externas', 'index.php?page=noticias', 'newspaper', NULL, 3, 1, 0),
(4, 'Sistemas', 'index.php?page=sistemas', 'apps', NULL, 4, 1, 0);

-- --------------------------------------------------------

--
-- Estrutura para tabela `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(300) NOT NULL,
  `summary` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `cover_image_alt` varchar(255) DEFAULT NULL,
  `cover_image_caption` text DEFAULT NULL,
  `type` enum('comunicado','noticia') NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `author_id` int(11) NOT NULL,
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `is_featured` tinyint(1) DEFAULT 0,
  `is_public` tinyint(1) DEFAULT 1,
  `views` int(11) DEFAULT 0,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `posts`
--

INSERT INTO `posts` (`id`, `title`, `slug`, `summary`, `content`, `cover_image`, `cover_image_alt`, `cover_image_caption`, `type`, `category_id`, `author_id`, `status`, `is_featured`, `is_public`, `views`, `published_at`, `created_at`, `updated_at`) VALUES
(1, 'Bem-vindos à Intranet', 'bem-vindos-a-intranet', 'A nova intranet centraliza acessos e comunicação institucional.', '<p>É com grande satisfação que anunciamos o lançamento da nova <strong>Intranet</strong>.</p><p>A plataforma centraliza o acesso aos sistemas e facilita a comunicação interna.</p><h2>Recursos disponíveis:</h2><ul><li>Acesso rápido aos sistemas</li><li>Comunicados e notícias</li><li>Módulos configuráveis</li><li>Modo escuro</li></ul>', NULL, '', '', 'comunicado', 1, 1, 'published', 1, 1, 0, '2026-04-09 11:22:10', '2026-04-09 11:22:10', '2026-04-09 11:22:10');

-- --------------------------------------------------------

--
-- Estrutura para tabela `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('text','textarea','image','boolean','color','json') DEFAULT 'text',
  `label` varchar(150) DEFAULT NULL,
  `group_name` varchar(80) DEFAULT 'general',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `label`, `group_name`, `updated_at`) VALUES
(1, 'site_name', 'Intranet', 'text', 'Nome do Site', 'general', '2026-04-09 11:22:10'),
(2, 'site_tagline', 'Portal Corporativo', 'text', 'Subtítulo', 'general', '2026-04-09 11:22:10'),
(3, 'site_logo', '', 'image', 'Logo', 'general', '2026-04-09 11:22:10'),
(4, 'footer_text', '© 2026 Minha Empresa. Todos os direitos reservados.', 'textarea', 'Rodapé', 'general', '2026-04-09 11:22:10'),
(5, 'primary_color', '#00897B', 'color', 'Cor Primária', 'appearance', '2026-04-09 11:22:10'),
(6, 'secondary_color', '#004D40', 'color', 'Cor Secundária', 'appearance', '2026-04-09 11:22:10'),
(7, 'posts_per_page', '10', 'text', 'Posts por Página', 'general', '2026-04-09 11:22:10'),
(8, 'allow_registration', '0', 'boolean', 'Permitir Auto-cadastro', 'auth', '2026-04-09 11:22:10'),
(9, 'session_timeout', '480', 'text', 'Timeout da Sessão (min)', 'auth', '2026-04-09 11:22:10'),
(10, 'hero_title', 'Minha Empresa', 'text', 'Título do Hero', 'appearance', '2026-04-09 11:22:10'),
(11, 'hero_subtitle', 'Portal de Comunicação Institucional', 'text', 'Subtítulo do Hero', 'appearance', '2026-04-09 11:22:10'),
(12, 'image_max_width', '1920', 'text', 'Largura máx. imagem (px)', 'media', '2026-04-09 11:22:10'),
(13, 'image_quality', '85', 'text', 'Qualidade JPEG (1-100)', 'media', '2026-04-09 11:22:10'),
(14, 'gallery_interval', '7', 'text', 'Intervalo do Carrossel (segundos)', 'media', '2026-04-09 11:22:10');

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(200) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','editor','user') DEFAULT 'user',
  `sector` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `dark_mode` tinyint(1) DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `users`
-- Senha de exemplo para os dois usuários abaixo: admin123
-- TROQUE IMEDIATAMENTE após o primeiro acesso.
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `sector`, `avatar`, `active`, `dark_mode`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'Administrador', 'admin@example.com', '$2y$12$XtzNpMP1Hw4nql0qVUmXv.oYzoGkkoRZcBqSjdIWTh93cnr0ANf0i', 'admin', 'TI', NULL, 1, 0, NULL, '2026-04-09 11:22:10', '2026-04-09 11:22:10'),
(2, 'Equipe Comunicação', 'comunicacao@example.com', '$2y$12$XtzNpMP1Hw4nql0qVUmXv.oYzoGkkoRZcBqSjdIWTh93cnr0ANf0i', 'editor', 'Comunicação', NULL, 1, 0, NULL, '2026-04-09 11:22:10', '2026-04-09 11:22:10');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Índices de tabela `modules`
--
ALTER TABLE `modules`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `nav_items`
--
ALTER TABLE `nav_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Índices de tabela `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `author_id` (`author_id`);

--
-- Índices de tabela `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `modules`
--
ALTER TABLE `modules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de tabela `nav_items`
--
ALTER TABLE `nav_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de tabela `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `nav_items`
--
ALTER TABLE `nav_items`
  ADD CONSTRAINT `nav_items_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `nav_items` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `posts_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `posts_ibfk_2` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

-- --------------------------------------------------------
-- Galeria de fotos por post
-- --------------------------------------------------------
CREATE TABLE `post_gallery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  CONSTRAINT `gallery_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Vídeos por post (YouTube, Vimeo, MP4)
-- --------------------------------------------------------
CREATE TABLE `post_videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `video_type` enum('youtube','vimeo','mp4') DEFAULT 'youtube',
  `video_url` varchar(500) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  CONSTRAINT `video_post_fk` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
