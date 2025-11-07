<?php
/**
 * All-in-one PHP Stack Inspector
 * --------------------------------
 * Single file to drop inside a container and see:
 * - PHP version, SAPI, extensions
 * - INI files
 * - Opcache, memory, limits
 * - Docker / OS / hostname clues
 * - Web server info
 * - Env vars (with naive secret filter)
 * - Composer presence, git info, etc. when possible
 *
 * ⚠️ À NE PAS UTILISER EN PROD (vraiment).
 */

declare(strict_types=1);

/*----------------- Config minimale -----------------*/

$TITLE = 'PHP Stack Inspector';
$SECRET_PATTERNS = [
    'password', 'passwd', 'pass', 'secret', 'key', 'token', 'apikey', 'api_key',
    'authorization', 'auth', 'cookie', 'jwt', 'ssh', 'private'
];

/*----------------- Helpers -----------------*/

function h(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function yesNo(bool $v): string {
    return $v ? '✅' : '❌';
}

function bytesToHuman($bytes): string {
    if (!is_numeric($bytes)) {
        return (string)$bytes;
    }
    $units = ['B','KB','MB','GB','TB'];
    $bytes = (float)$bytes;
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return sprintf('%.2f&nbsp;%s', $bytes, $units[$i]);
}

function iniBytesToHuman(string $val): string {
    $val = trim($val);
    if ($val === '' || $val === '-1') {
        return $val === '-1' ? 'Illimité (-1)' : 'Non défini';
    }
    $last = strtolower($val[strlen($val)-1]);
    $num = (float)$val;
    switch ($last) {
        case 'g': $num *= 1024;
        case 'm': $num *= 1024;
        case 'k': $num *= 1024;
    }
    return bytesToHuman($num) . " ({$val})";
}

function looksLikeSecretKey(string $name): bool {
    global $SECRET_PATTERNS;
    $n = strtolower($name);
    foreach ($SECRET_PATTERNS as $pat) {
        if (str_contains($n, $pat)) {
            return true;
        }
    }
    return false;
}

function readFileSafe(string $path, int $max = 8192): ?string {
    if (!is_readable($path) || is_dir($path)) {
        return null;
    }
    $c = @file_get_contents($path, false, null, 0, $max);
    return $c === false ? null : trim($c);
}

function shellExecSafe(string $cmd): ?string {
    if (!function_exists('shell_exec')) {
        return null;
    }
    $out = @shell_exec($cmd . ' 2>/dev/null');
    return $out ? trim($out) : null;
}

/*----------------- Collecte d'infos -----------------*/

$phpVersion      = PHP_VERSION;
$phpSapi         = PHP_SAPI;
$phpOs           = PHP_OS;
$phpInterface    = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
$memoryLimit     = ini_get('memory_limit') ?: 'N/A';
$uploadMax       = ini_get('upload_max_filesize') ?: 'N/A';
$postMax         = ini_get('post_max_size') ?: 'N/A';
$maxExec         = ini_get('max_execution_time') ?: 'N/A';
$displayErrors   = ini_get('display_errors') ?: '0';

$loadedExtensions = get_loaded_extensions();
sort($loadedExtensions);

$iniMain     = php_ini_loaded_file();
$iniScanned  = php_ini_scanned_files();

$opcache = function_exists('opcache_get_status')
    ? @opcache_get_status(false) ?: null
    : null;

$env = $_SERVER + $_ENV; // fusion basique

// OS & Docker hints
$osRelease   = readFileSafe('/etc/os-release');
$cgroup      = readFileSafe('/proc/1/cgroup', 4096);
$inDocker    = false;
if ($cgroup && (str_contains($cgroup, 'docker') || str_contains($cgroup, 'kubepods'))) {
    $inDocker = true;
}
if (is_file('/.dockerenv')) {
    $inDocker = true;
}

$hostname    = gethostname() ?: ($_SERVER['HOSTNAME'] ?? 'N/A');
$whoami      = function_exists('get_current_user') ? get_current_user() : 'N/A';

$composer    = shellExecSafe('composer --version');
$gitBranch   = shellExecSafe('git rev-parse --abbrev-ref HEAD');
$gitCommit   = shellExecSafe('git rev-parse --short HEAD');

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
$scriptFile   = __FILE__;
$cwd          = getcwd();

/*----------------- HTML -----------------*/
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= h($TITLE) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #050816;
            --bg-soft: #0f172a;
            --bg-soft-2: #111827;
            --accent: #38bdf8;
            --accent-soft: rgba(56,189,248,0.18);
            --accent-alt: #a855f7;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --danger: #f97316;
            --radius-xl: 24px;
            --radius: 14px;
            --shadow-soft: 0 18px 60px rgba(15,23,42,0.85);
            --border-soft: 1px solid rgba(148,163,253,0.12);
            --font: system-ui, -apple-system, BlinkMacSystemFont, "SF Pro Text", -system-ui, sans-serif;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 32px;
            font-family: var(--font);
            background: radial-gradient(circle at top, #0f172a 0, #020817 45%, #000 100%);
            color: var(--text);
        }

        .layout {
            max-width: 1480px;
            margin: 0 auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            margin-bottom: 28px;
        }

        .title-block h1 {
            font-size: 32px;
            margin: 0 0 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .title-pill {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 999px;
            border: 1px solid rgba(56,189,248,0.3);
            color: var(--accent);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: radial-gradient(circle at top left, rgba(56,189,248,0.12), transparent);
        }

        .subtitle {
            margin: 8px 0 0;
            font-size: 13px;
            color: var(--muted);
        }

        .tagline {
            margin-top: 4px;
            font-size: 11px;
            color: var(--danger);
            opacity: 0.9;
        }

        .pill-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            font-size: 10px;
        }

        .pill {
            padding: 4px 9px;
            border-radius: 999px;
            border: 1px solid rgba(148,163,253,0.18);
            background: rgba(15,23,42,0.9);
            color: var(--muted);
            display: inline-flex;
            gap: 6px;
            align-items: center;
        }

        .pill strong {
            color: var(--accent);
            font-weight: 500;
        }

        .warning {
            font-size: 10px;
            color: var(--danger);
            text-align: right;
            opacity: .9;
        }

        main {
            display: grid;
            grid-template-columns: 2.1fr 1.6fr;
            gap: 18px;
        }

        .card {
            background: radial-gradient(circle at top left, rgba(56,189,248,0.06), transparent) ,
                        radial-gradient(circle at top right, rgba(168,85,247,0.05), transparent),
                        var(--bg-soft);
            border-radius: var(--radius-xl);
            padding: 14px 14px 12px;
            box-shadow: var(--shadow-soft);
            border: var(--border-soft);
            backdrop-filter: blur(14px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 10px;
            margin-bottom: 8px;
        }

        .card-title {
            font-size: 14px;
            font-weight: 500;
            letter-spacing: .02em;
            color: var(--accent);
            display: inline-flex;
            gap: 6px;
            align-items: center;
        }

        .card-sub {
            font-size: 10px;
            color: var(--muted);
        }

        .label {
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 999px;
            border: 1px solid rgba(56,189,248,0.25);
            color: var(--accent-alt);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        th, td {
            padding: 4px 4px;
            vertical-align: top;
        }

        th {
            text-align: left;
            color: var(--muted);
            font-weight: 500;
            font-size: 9px;
            border-bottom: 1px solid rgba(75,85,99,0.6);
        }

        tr:nth-child(even) td {
            background: rgba(15,23,42,0.78);
        }

        tr:nth-child(odd) td {
            background: rgba(9,9,15,0.85);
        }

        td.key {
            width: 34%;
            color: var(--accent);
            white-space: nowrap;
        }

        td.val {
            color: var(--text);
            word-break: break-all;
        }

        code {
            font-family: "SF Mono", Menlo, Consolas, monospace;
            font-size: 9px;
            padding: 1px 4px;
            border-radius: 6px;
            background: rgba(15,23,42,0.98);
            border: 1px solid rgba(75,85,99,0.7);
        }

        .ext-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 3px 6px;
            font-size: 9px;
            margin-top: 2px;
        }

        .ext-pill {
            border-radius: 10px;
            padding: 3px 6px;
            background: rgba(9,9,15,0.95);
            border: 1px solid rgba(75,85,99,0.7);
            color: var(--muted);
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .ext-pill span {
            font-size: 7px;
            color: var(--accent-alt);
        }

        .hint {
            font-size: 8px;
            color: var(--muted);
            margin-top: 3px;
        }

        pre.small {
            margin: 4px 0 0;
            padding: 4px 6px;
            font-size: 8px;
            line-height: 1.4;
            max-height: 120px;
            overflow: auto;
            background: rgba(3,7,18,0.98);
            border-radius: 8px;
            border: 1px solid rgba(75,85,99,0.6);
        }

        .split {
            display: grid;
            grid-template-columns: 1.6fr 1.4fr;
            gap: 10px;
        }

        @media (max-width: 1024px) {
            body { padding: 16px; }
            main { grid-template-columns: 1fr; }
            header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
<div class="layout">
    <header>
        <div class="title-block">
            <div class="title-pill">
                <span>🛰️</span>
                <span>Runtime Intel Probe</span>
            </div>
            <h1><?= h($TITLE) ?> <span style="font-size:16px;color:var(--accent-alt);">1.0.0</span></h1>
            <p class="subtitle">
                Vue synthétique de la stack <strong>PHP / Docker / OS</strong>
            </p>
            <p class="tagline">Ne laisse <u>jamais</u> ce fichier traîner en production.</p>
            <div class="pill-row">
                <div class="pill"><strong>PHP</strong><?= h($phpVersion) ?></div>
                <div class="pill"><strong>SAPI</strong><?= h($phpSapi) ?></div>
                <div class="pill"><strong>OS</strong><?= h($phpOs) ?></div>
                <div class="pill"><strong>Server</strong><?= h($phpInterface) ?></div>
                <div class="pill"><strong>Docker</strong><?= $inDocker ? 'Probable ✅' : 'Non détecté ❓' ?></div>
                <div class="pill"><strong>Host</strong><?= h($hostname) ?></div>
                <div class="pill"><strong>User</strong><?= h($whoami) ?></div>
                <?php if ($gitBranch): ?>
                    <div class="pill"><strong>Git</strong><?= h($gitBranch) ?>@<?= h((string)$gitCommit) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div style="min-width:210px;text-align:right;">
            <div class="warning">⚠ Infos sensibles possibles.<br>Supprime après usage.</div>
        </div>
    </header>

    <main>
        <!-- Colonne gauche -->
        <section>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Runtime &amp; Limits</div>
                        <div class="card-sub">Paramètres clés de l’exécution PHP</div>
                    </div>
                    <div class="label">core</div>
                </div>
                <table>
                    <tr>
                        <td class="key">PHP Version</td>
                        <td class="val"><?= h($phpVersion) ?></td>
                    </tr>
                    <tr>
                        <td class="key">SAPI</td>
                        <td class="val"><?= h($phpSapi) ?></td>
                    </tr>
                    <tr>
                        <td class="key">Memory limit</td>
                        <td class="val"><?= iniBytesToHuman($memoryLimit) ?></td>
                    </tr>
                    <tr>
                        <td class="key">upload_max_filesize</td>
                        <td class="val"><?= iniBytesToHuman($uploadMax) ?></td>
                    </tr>
                    <tr>
                        <td class="key">post_max_size</td>
                        <td class="val"><?= iniBytesToHuman($postMax) ?></td>
                    </tr>
                    <tr>
                        <td class="key">max_execution_time</td>
                        <td class="val"><?= h((string)$maxExec) ?> s</td>
                    </tr>
                    <tr>
                        <td class="key">display_errors</td>
                        <td class="val"><?= $displayErrors ? 'On ⚠️' : 'Off ✅' ?></td>
                    </tr>
                </table>
            </div>

            <div class="card" style="margin-top:10px;">
                <div class="card-header">
                    <div>
                        <div class="card-title">PHP Extensions</div>
                        <div class="card-sub">Ce qui est réellement chargé</div>
                    </div>
                    <div class="label"><?= count($loadedExtensions) ?> ext</div>
                </div>
                <div class="ext-grid">
                    <?php foreach ($loadedExtensions as $ext): ?>
                        <div class="ext-pill">
                            <?= h($ext) ?>
                            <?php if (in_array($ext, ['pdo_mysql','mysqli','pgsql','redis','curl','swoole','xdebug','opcache'], true)): ?>
                                <span>•</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="hint">
                    • Points: <code>•</code> = extensions souvent critiques (db, perf, debug, etc.).
                </div>
            </div>

            <div class="card" style="margin-top:10px;">
                <div class="card-header">
                    <div>
                        <div class="card-title">Opcache &amp; Performance</div>
                        <div class="card-sub">Etat rapide du cache opcode</div>
                    </div>
                    <div class="label">perf</div>
                </div>
                <?php if ($opcache && !empty($opcache['opcache_enabled'])): ?>
                    <table>
                        <tr>
                            <td class="key">opcache_enabled</td>
                            <td class="val"><?= yesNo((bool)$opcache['opcache_enabled']) ?></td>
                        </tr>
                        <tr>
                            <td class="key">memory_usage</td>
                            <td class="val">
                                <?= bytesToHuman($opcache['memory_usage']['used_memory'] ?? 0) ?> used /
                                <?= bytesToHuman($opcache['memory_usage']['free_memory'] ?? 0) ?> free
                            </td>
                        </tr>
                        <tr>
                            <td class="key">cached scripts</td>
                            <td class="val"><?= h((string)($opcache['opcache_statistics']['num_cached_scripts'] ?? 0)) ?></td>
                        </tr>
                        <tr>
                            <td class="key">hits / misses</td>
                            <td class="val">
                                <?= h((string)($opcache['opcache_statistics']['hits'] ?? 0)) ?>
                                hits /
                                <?= h((string)($opcache['opcache_statistics']['misses'] ?? 0)) ?> misses
                            </td>
                        </tr>
                    </table>
                <?php else: ?>
                    <div class="hint">
                        <?= function_exists('opcache_get_status')
                            ? "Opcache semble désactivé sur cette stack."
                            : "Fonction opcache_get_status indisponible sur ce runtime."; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Colonne droite -->
        <section>
            <div class="card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Container / OS Context</div>
                        <div class="card-sub">Indices sur le runtime sous-jacent</div>
                    </div>
                    <div class="label"><?= $inDocker ? 'docker-ish' : 'host-ish' ?></div>
                </div>
                <div class="split">
                    <table>
                        <tr>
                            <td class="key">Hostname</td>
                            <td class="val"><?= h($hostname) ?></td>
                        </tr>
                        <tr>
                            <td class="key">User</td>
                            <td class="val"><?= h($whoami) ?></td>
                        </tr>
                        <tr>
                            <td class="key">DOCUMENT_ROOT</td>
                            <td class="val"><?= h((string)$documentRoot) ?></td>
                        </tr>
                        <tr>
                            <td class="key">Script</td>
                            <td class="val"><code><?= h($scriptFile) ?></code></td>
                        </tr>
                        <tr>
                            <td class="key">CWD</td>
                            <td class="val"><code><?= h((string)$cwd) ?></code></td>
                        </tr>
                        <?php if ($composer): ?>
                            <tr>
                                <td class="key">Composer</td>
                                <td class="val"><?= h($composer) ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($gitBranch): ?>
                            <tr>
                                <td class="key">Git</td>
                                <td class="val">
                                    <?= h($gitBranch) ?>
                                    <?php if ($gitCommit): ?>
                                        <code><?= h($gitCommit) ?></code>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </table>
                    <div>
                        <div class="hint"><strong>/etc/os-release</strong></div>
                        <pre class="small"><?= h($osRelease ?? 'Non disponible') ?></pre>
                        <div class="hint" style="margin-top:3px;"><strong>/proc/1/cgroup</strong></div>
                        <pre class="small"><?= h($cgroup ?? 'Non disponible') ?></pre>
                    </div>
                </div>
            </div>

            <div class="card" style="margin-top:10px;">
                <div class="card-header">
                    <div>
                        <div class="card-title">Configuration PHP (INI)</div>
                        <div class="card-sub">Fichiers de configuration chargés</div>
                    </div>
                    <div class="label">ini</div>
                </div>
                <table>
                    <tr>
                        <td class="key">php.ini principal</td>
                        <td class="val"><?= h((string)($iniMain ?: 'Non trouvé')) ?></td>
                    </tr>
                    <tr>
                        <td class="key">Scanned ini</td>
                        <td class="val">
                            <?php if ($iniScanned): ?>
                                <code><?= h($iniScanned) ?></code>
                            <?php else: ?>
                                Aucun ou indisponible
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="card" style="margin-top:10px;">
                <div class="card-header">
                    <div>
                        <div class="card-title">Environment Snapshot</div>
                        <div class="card-sub">Variables (sélectives) serveur &amp; env</div>
                    </div>
                    <div class="label">env</div>
                </div>
                <table>
                    <tr>
                        <th>Clé</th>
                        <th>Valeur</th>
                    </tr>
                    <?php
                    ksort($env);
                    $count = 0;
                    foreach ($env as $k => $v):
                        if (!is_string($k)) continue;
                        if (looksLikeSecretKey($k)) continue; // skip naïf
                        if (is_array($v)) $v = json_encode($v, JSON_UNESCAPED_SLASHES);
                        $count++;
                        if ($count > 200) break; // hard cap
                    ?>
                        <tr>
                            <td class="key"><?= h($k) ?></td>
                            <td class="val"><?= h((string)$v) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <div class="hint">
                    Filtrage naïf des clés contenant : <?= h(implode(', ', $SECRET_PATTERNS)) ?>.
                    Vérifie avant d'envoyer un screenshot.
                </div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
