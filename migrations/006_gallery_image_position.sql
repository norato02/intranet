-- Posição de enquadramento por foto da galeria/slider (mesmo recurso
-- de arrastar/zoom que a capa já tinha, agora por imagem)
ALTER TABLE `post_gallery` ADD COLUMN `image_position` varchar(20) DEFAULT '50% 50%' AFTER `caption`;
