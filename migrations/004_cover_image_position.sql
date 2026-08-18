-- Posição de enquadramento da imagem de capa (arraste/zoom no editor de posts)
ALTER TABLE `posts` ADD COLUMN `cover_image_position` varchar(20) DEFAULT '50% 50%' AFTER `media_type`;
