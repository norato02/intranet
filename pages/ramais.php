<?php
$busca = trim($_GET['q'] ?? ''); $andarF = trim($_GET['andar'] ?? ''); $where = 'WHERE active = 1'; $params = [];
if ($busca) { $where .= ' AND (setor LIKE ? OR ramal LIKE ? OR linha LIKE ? OR andar LIKE ?)'; $params = ["%$busca%","%$busca%","%$busca%","%$busca%"]; }
if ($andarF) { $where .= ' AND andar = ?'; $params[] = $andarF; }
$todosRamais = Database::fetchAll("SELECT * FROM ramais $where ORDER BY sort_order, andar, setor", $params);
$andares = Database::fetchAll('SELECT DISTINCT andar FROM ramais WHERE active=1 ORDER BY sort_order, andar');
$totalRamais = count($todosRamais); $totalLinhas = count(array_filter(array_column($todosRamais,'linha'),fn($l)=>$l!=='')); $totalAndares = count($andares);
$porAndar = []; foreach ($todosRamais as $r) { $porAndar[$r['andar']][] = $r; }
?>
<div class="container main-content fade-in">
  <div class="section-header" style="margin-bottom:20px">
    <span class="section-title"><span class="material-icons">phone_in_talk</span> Lista de Ramais</span>
    <button onclick="window.print()" class="btn btn-primary btn-sm" style="display:flex;align-items:center;gap:6px"><span class="material-icons" style="font-size:16px">print</span> Imprimir</button>
  </div>
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px" class="ramais-stats">
    <div class="card" style="padding:18px 20px;display:flex;align-items:center;gap:14px"><div style="width:44px;height:44px;border-radius:12px;background:var(--primary-xlight);display:flex;align-items:center;justify-content:center"><span class="material-icons" style="color:var(--primary)">phone</span></div><div><div style="font-size:24px;font-weight:800;color:var(--primary);line-height:1"><?= $totalRamais ?></div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-top:2px">Ramais</div></div></div>
    <div class="card" style="padding:18px 20px;display:flex;align-items:center;gap:14px"><div style="width:44px;height:44px;border-radius:12px;background:var(--primary-xlight);display:flex;align-items:center;justify-content:center"><span class="material-icons" style="color:var(--primary)">layers</span></div><div><div style="font-size:24px;font-weight:800;color:var(--primary);line-height:1"><?= $totalAndares ?></div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-top:2px">Andares</div></div></div>
    <div class="card" style="padding:18px 20px;display:flex;align-items:center;gap:14px"><div style="width:44px;height:44px;border-radius:12px;background:var(--primary-xlight);display:flex;align-items:center;justify-content:center"><span class="material-icons" style="color:var(--primary)">call</span></div><div><div style="font-size:24px;font-weight:800;color:var(--primary);line-height:1"><?= $totalLinhas ?></div><div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-top:2px">Linhas Diretas</div></div></div>
  </div>
  <div class="card" style="padding:14px 18px;margin-bottom:20px">
    <form method="GET" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="page" value="ramais">
      <div style="flex:1;min-width:200px;position:relative"><span class="material-icons" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--primary);font-size:17px">search</span><input type="text" name="q" value="<?= htmlspecialchars($busca) ?>" placeholder="Buscar por setor, ramal ou linha…" style="width:100%;padding:9px 14px 9px 38px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;background:var(--bg);color:var(--text);outline:none;font-family:var(--font-body)"></div>
      <select name="andar" style="padding:9px 14px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:14px;background:var(--bg);color:var(--text);outline:none;cursor:pointer;min-width:160px"><option value="">Todos os andares</option><?php foreach ($andares as $a): ?><option value="<?= htmlspecialchars($a['andar']) ?>" <?= $andarF===$a['andar']?'selected':'' ?>><?= htmlspecialchars($a['andar']) ?></option><?php endforeach; ?></select>
      <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
      <?php if ($busca || $andarF): ?><a href="?page=ramais" class="btn btn-ghost btn-sm">Limpar</a><?php endif; ?>
      <span style="font-size:13px;color:var(--text-muted);white-space:nowrap"><strong style="color:var(--primary)"><?= $totalRamais ?></strong> ramal(is)</span>
    </form>
  </div>
  <div class="card" style="overflow:hidden;padding:0"><div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse">
    <thead><tr style="background:var(--primary)">
      <th style="padding:13px 18px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#fff;white-space:nowrap"><span class="material-icons" style="font-size:13px;vertical-align:middle;margin-right:5px">layers</span>Andar</th>
      <th style="padding:13px 18px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#fff"><span class="material-icons" style="font-size:13px;vertical-align:middle;margin-right:5px">business</span>Setor</th>
      <th style="padding:13px 18px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#fff;white-space:nowrap"><span class="material-icons" style="font-size:13px;vertical-align:middle;margin-right:5px">phone</span>Ramal</th>
      <th style="padding:13px 18px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.7px;color:#fff;white-space:nowrap"><span class="material-icons" style="font-size:13px;vertical-align:middle;margin-right:5px">call</span>Linha Direta</th>
    </tr></thead>
    <tbody>
    <?php foreach ($porAndar as $andar => $itens): ?>
    <tr style="background:var(--primary-xlight)"><td colspan="4" style="padding:7px 18px;font-size:11px;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:1px;border-bottom:1px solid var(--border)"><span class="material-icons" style="font-size:13px;vertical-align:middle;margin-right:5px">place</span><?= htmlspecialchars($andar) ?></td></tr>
    <?php foreach ($itens as $r): ?>
    <tr style="border-bottom:1px solid var(--border);transition:background .15s" onmouseover="this.style.background='var(--primary-xlight)'" onmouseout="this.style.background=''">
      <td style="padding:12px 18px;vertical-align:middle;white-space:nowrap"><span style="display:inline-flex;align-items:center;gap:4px;background:var(--primary-xlight);color:var(--primary);border:1px solid var(--border);padding:3px 9px;border-radius:20px;font-size:11px;font-weight:700"><span class="material-icons" style="font-size:11px">layers</span><?= htmlspecialchars($r['andar']) ?></span></td>
      <td style="padding:12px 18px;font-size:14px;font-weight:500;color:var(--text);vertical-align:middle"><?= htmlspecialchars($r['setor']) ?></td>
      <td style="padding:12px 18px;vertical-align:middle;white-space:nowrap"><span style="display:inline-flex;align-items:center;gap:5px;background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;padding:3px 11px;border-radius:20px;font-size:13px;font-weight:700"><span class="material-icons" style="font-size:13px">phone_in_talk</span><?= htmlspecialchars($r['ramal']) ?></span></td>
      <td style="padding:12px 18px;font-size:13px;color:var(--text-muted);vertical-align:middle;white-space:nowrap"><?php if ($r['linha']): ?><span style="display:inline-flex;align-items:center;gap:5px"><span class="material-icons" style="font-size:13px;color:var(--primary)">call</span><?= htmlspecialchars($r['linha']) ?></span><?php else: ?><span style="color:var(--border)">—</span><?php endif; ?></td>
    </tr>
    <?php endforeach; endforeach; ?>
    <?php if (empty($porAndar)): ?><tr><td colspan="4" style="padding:56px;text-align:center;color:var(--text-muted)">Nenhum ramal encontrado.</td></tr><?php endif; ?>
    </tbody>
  </table></div></div>
</div>
<style>
@media(max-width:640px){ .ramais-stats{ grid-template-columns:1fr!important; } }
@media print { .navbar,button,form,.ramais-stats{display:none!important} .card{box-shadow:none!important;border:1px solid #ccc!important} thead tr{-webkit-print-color-adjust:exact;print-color-adjust:exact} }
</style>
