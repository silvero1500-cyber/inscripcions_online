<?php
if (($_GET['t'] ?? '') !== 'm_4w') { http_response_code(404); exit('Not Found'); }
header('Content-Type: text/plain; charset=utf-8');
require __DIR__ . '/bootstrap/autoload.php';
\App\Core\Env::load(__DIR__ . '/.env');
$pdo = \App\Core\Database::getInstance()->pdo();

$files = glob(__DIR__.'/database/migration_*.sql') ?: []; sort($files,SORT_STRING);
$applied = $pdo->query("SELECT filename FROM schema_migrations")->fetchAll(PDO::FETCH_COLUMN);
$set = array_flip($applied);
echo "=== MIGRACIONS (fitxer → registrada com aplicada?) ===\n";
$pend=0;
foreach($files as $f){ $n=basename($f); $ok=isset($set[$n]); if(!$ok)$pend++; echo ($ok?'  ✔ ':'  ✘ PENDENT ').$n."\n"; }
echo "Total fitxers: ".count($files)." · registrades: ".count($applied)." · pendents: $pend\n\n";

echo "=== OBJECTES REALS A LA BD ===\n";
function tbl($pdo,$t){ return $pdo->query("SHOW TABLES LIKE '$t'")->fetch()?'OK':'FALTA'; }
function col($pdo,$t,$c){ return $pdo->query("SHOW COLUMNS FROM $t LIKE '$c'")->fetch()?'OK':'FALTA'; }
foreach(['tienda_productos','tienda_producto_variaciones','tienda_pedidos','tarifa_precios','ajustes','recollida_log','rate_limits','lista_espera'] as $t) echo "  taula $t: ".tbl($pdo,$t)."\n";
echo "  pagos.tienda_pedido_id: ".col($pdo,'pagos','tienda_pedido_id')."\n";
echo "  inscritos.precio_aplicado: ".col($pdo,'inscritos','precio_aplicado')."\n";
echo "  usuarios.rol té 'recollida': ".(strpos($pdo->query("SHOW COLUMNS FROM usuarios LIKE 'rol'")->fetch()['Type'],"'recollida'")!==false?'OK':'FALTA')."\n";
echo "DONE\n";
