<?php
// ============================================================
// MIGRATE — aplica automaticamente as migrações de banco pendentes
// ============================================================
// Uso:
//   • Navegador: abra /intranet-acqua/migrate.php logado como admin.
//   • Linha de comando: php migrate.php
//
// Como funciona:
//   Cada atualização do sistema que precisar de mudança no banco
//   ganha um arquivo novo em migrations/ (ex: 006_algo_novo.sql).
//   Este script confere quais já foram aplicadas (tabela
//   schema_migrations) e roda só as que faltam, na ordem do nome
//   do arquivo. Rodar de novo não tem problema — o que já foi
//   aplicado é pulado.
//
//   Sempre usa as credenciais de includes/db_config.php do próprio
//   servidor, então funciona igual em dev, homologação e produção
//   sem precisar editar nada nos arquivos de migração.
// ============================================================

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/functions.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    requireAdmin();
}

function isAlreadyExistsError(PDOException $e): bool {
    // 1060 Duplicate column, 1061 Duplicate key name, 1050 Table already exists,
    // 1826 Duplicate foreign key constraint name — safe to ignore on a re-run.
    $code = $e->errorInfo[1] ?? null;
    if (in_array($code, [1060, 1061, 1050, 1826], true)) return true;
    return (bool) preg_match('/already exists|duplicate column|duplicate key name/i', $e->getMessage());
}

function splitSqlStatements(string $sql): array {
    $lines = explode("\n", $sql);
    $clean = [];
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '--')) continue;
        $clean[] = $line;
    }
    $statements = explode(';', implode("\n", $clean));
    $statements = array_map('trim', $statements);
    return array_values(array_filter($statements, fn($s) => $s !== ''));
}

$pdo = Database::getInstance();

$pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `applied_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `migration` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$already = array_column(Database::fetchAll('SELECT migration FROM schema_migrations'), 'migration');

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files, SORT_STRING);

$results = [];
$failed  = false;

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $already, true)) {
        $results[] = ['file' => $name, 'status' => 'skip', 'msg' => 'já aplicada'];
        continue;
    }
    if ($failed) {
        $results[] = ['file' => $name, 'status' => 'pending', 'msg' => 'não executada (migração anterior falhou)'];
        continue;
    }
    $statements = splitSqlStatements(file_get_contents($file));
    $skippedParts = 0;
    try {
        foreach ($statements as $stmt) {
            try {
                $pdo->exec($stmt);
            } catch (PDOException $e) {
                if (!isAlreadyExistsError($e)) throw $e;
                $skippedParts++;
            }
        }
        Database::query('INSERT INTO schema_migrations (migration) VALUES (?)', [$name]);
        $msg = $skippedParts > 0 ? 'aplicada (parte já existia no banco)' : 'aplicada com sucesso';
        $results[] = ['file' => $name, 'status' => 'ok', 'msg' => $msg];
    } catch (Throwable $e) {
        $results[] = ['file' => $name, 'status' => 'error', 'msg' => $e->getMessage()];
        $failed = true;
    }
}

if ($isCli) {
    foreach ($results as $r) {
        $tag = ['ok' => '[OK]   ', 'skip' => '[--]   ', 'error' => '[ERRO] ', 'pending' => '[FALTA]'][$r['status']];
        echo $tag . ' ' . $r['file'] . ' — ' . $r['msg'] . PHP_EOL;
    }
    echo $failed ? "\nMigração interrompida por erro.\n" : "\nBanco atualizado.\n";
    exit($failed ? 1 : 0);
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="<?= ($_SESSION['dark_mode'] ?? false) ? 'dark' : 'light' ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Migração do Banco — Admin</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= assetVersion('assets/css/style.css') ?>">
  <?= getColorVarsStyle() ?>
</head>
<body class="fade-in" style="padding:40px 20px">
  <div class="card card-body" style="max-width:680px;margin:0 auto">
    <h1 style="font-size:20px;margin-bottom:4px">Migração do Banco de Dados</h1>
    <p style="color:var(--text-muted);font-size:13px;margin-bottom:20px">
      Banco: <code><?= htmlspecialchars(DB_NAME) ?></code> em <code><?= htmlspecialchars(DB_HOST) ?></code>
    </p>
    <?php if (empty($results)): ?>
      <div class="alert alert-info"><span class="material-icons">check_circle</span> Nenhuma migração encontrada em <code>migrations/</code>.</div>
    <?php else: foreach ($results as $r):
      $cls  = ['ok' => 'alert-success', 'skip' => 'alert-info', 'error' => 'alert-danger', 'pending' => 'alert-warning'][$r['status']];
      $icon = ['ok' => 'check_circle', 'skip' => 'remove_circle_outline', 'error' => 'error', 'pending' => 'schedule'][$r['status']];
    ?>
      <div class="alert <?= $cls ?>" style="margin-bottom:8px;font-size:13px">
        <span class="material-icons"><?= $icon ?></span>
        <strong><?= htmlspecialchars($r['file']) ?></strong> — <?= htmlspecialchars($r['msg']) ?>
      </div>
    <?php endforeach; endif; ?>
    <div style="margin-top:20px;display:flex;gap:10px">
      <a href="migrate.php" class="btn btn-outline btn-sm"><span class="material-icons">refresh</span> Rodar de novo</a>
      <a href="admin/index.php" class="btn btn-primary btn-sm"><span class="material-icons">arrow_back</span> Voltar ao Admin</a>
    </div>
  </div>
</body>
</html>
