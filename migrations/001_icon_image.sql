-- Ícone customizado (imagem) para módulos, além do ícone Material Icons
ALTER TABLE `modules` ADD COLUMN `icon_image` varchar(255) DEFAULT NULL AFTER `icon`;
