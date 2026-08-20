-- Formato/proporção do card de destaque, escolhido pelo editor na aba
-- Preview (análise mostrou que a maioria dos posts usa fotos 4:3 ou
-- quadradas, não o formato panorâmico 1.86:1 que a home forçava antes).
ALTER TABLE `posts` ADD COLUMN `cover_aspect_ratio` varchar(10) DEFAULT '1.33' AFTER `cover_image_position`;
