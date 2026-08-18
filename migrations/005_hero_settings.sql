-- Textos do hero da página inicial (editáveis depois em Admin → Configurações)
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `label`, `group_name`) VALUES
('hero_title',    'Minha Empresa',                        'text', 'Título do Hero',    'appearance'),
('hero_subtitle', 'Portal de Comunicação Institucional',  'text', 'Subtítulo do Hero', 'appearance');
