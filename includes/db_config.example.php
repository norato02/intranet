<?php
// ============================================================
// CONFIGURAÇÃO DO BANCO DE DADOS — Intranet
// ============================================================
// Copie este arquivo para "db_config.php" (mesma pasta) e
// preencha com os dados reais do seu banco. O db_config.php
// nunca é versionado (está no .gitignore) — assim cada ambiente
// mantém suas próprias credenciais, e atualizações futuras do
// código não sobrescrevem nem expõem esses dados.
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'intranet');
define('DB_USER',    'usuario_mysql');
define('DB_PASS',    'senha_mysql');
define('DB_CHARSET', 'utf8mb4');
