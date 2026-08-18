-- Mídia de destaque nos posts: vídeo de capa (YouTube/Vimeo/MP4) e tipo de mídia
ALTER TABLE `posts` ADD COLUMN `cover_video_url`  varchar(500) DEFAULT NULL AFTER `cover_image_caption`;
ALTER TABLE `posts` ADD COLUMN `cover_video_type` enum('youtube','vimeo','mp4') DEFAULT NULL AFTER `cover_video_url`;
ALTER TABLE `posts` ADD COLUMN `media_type`       enum('image','video','slider') DEFAULT 'image' AFTER `cover_video_type`;

UPDATE `posts` SET `media_type` = 'image' WHERE `media_type` IS NULL;
