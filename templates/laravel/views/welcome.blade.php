{{-- resources/views/dev/allinone.blade.php --}}
@php
    // Sécurité soft : ne rendre la page que si local ou debug
    if (!app()->isLocal() && !config('app.debug')) {
        http_response_code(404);
        exit; // toute l'intelligence reste ici comme demandé
    }

    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\Route as RouteFacade;

    $appName   = config('app.name');
    $appEnv    = app()->environment();
    $debug     = config('app.debug') ? 'true' : 'false';
    $url       = config('app.url');
    $phpVer    = PHP_VERSION;
    $laravel   = app()->version();

    // Drivers / config clés
    $cacheDriver = config('cache.default');
    $queueDriver = config('queue.default');
    $mailDriver  = config('mail.default');
    $sessionDriver = config('session.driver');

    // Extensions utiles
    $extensions = [
        'pdo' => extension_loaded('pdo'),
        'openssl' => extension_loaded('openssl'),
        'mbstring' => extension_loaded('mbstring'),
        'curl' => extension_loaded('curl'),
        'fileinfo' => extension_loaded('fileinfo'),
        'json' => extension_loaded('json'),
        'bcmath' => extension_loaded('bcmath'),
        'intl' => extension_loaded('intl'),
        'redis' => extension_loaded('redis'),
        'pcntl' => extension_loaded('pcntl'),
    ];

    // DB check
    $dbOk = false; $dbErr = null; $dbDriver = config('database.default');
    try { DB::connection()->getPdo(); $dbOk = true; } catch (\Throwable $e) { $dbErr = $e->getMessage(); }

    // Cache check
    $cacheOk = false;
    try {
        Cache::put('__dev_check__', 'ok', 60);
        $cacheOk = Cache::get('__dev_check__') === 'ok';
    } catch (\Throwable $e) { /* ignore */ }

    // Storage symlink
    $storageLinked = is_link(public_path('storage')) || file_exists(public_path('storage'));

    // Composer packages (si Composer\InstalledVersions dispo)
    $pkgCount = null; $topPkgs = [];
    if (class_exists(\Composer\InstalledVersions::class)) {
        try {
            $allPkgs = \Composer\InstalledVersions::getInstalledPackages();
            $pkgCount = is_countable($allPkgs) ? count($allPkgs) : null;
            $topPkgs = array_slice($allPkgs, 0, 12);
        } catch (\Throwable $e) { /* ignore */ }
    }

    // Routes
    $routes = [];
    foreach (app('router')->getRoutes() as $r) {
        /** @var \Illuminate\Routing\Route $r */
        $action = $r->getActionName();
        if ($action === 'Closure') $action = 'Closure';
        $routes[] = [
            'method'     => implode('|', $r->methods()),
            'uri'        => $r->uri(),
            'name'       => $r->getName(),
            'action'     => $action,
            'middleware' => implode(', ', $r->gatherMiddleware()),
        ];
    }
    // Tri par URI pour stabilité
    usort($routes, fn($a,$b) => strcmp($a['uri'], $b['uri']));
@endphp


<?php
    // display absolute path
    $absPath = base_path();
    $homeDir = getenv('HOME') ?: (getenv('HOMEDRIVE') . getenv('HOMEPATH'));
    if ($homeDir && str_starts_with($absPath, $homeDir)) {
        $absPath = '~' . substr($absPath, strlen($homeDir));
    }
?>


<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $appName ? $appName.' — ' : '' }}Dev All-in-One</title>
<style>
    :root{ --bg:#0f172a; --card:#111827; --muted:#94a3b8; --ok:#22c55e; --ko:#ef4444; --warn:#f59e0b; --txt:#e5e7eb; --acc:#38bdf8; }
    *{box-sizing:border-box} html,body{margin:0;padding:0;background:var(--bg);color:var(--txt);font:14px/1.45 system-ui,Segoe UI,Roboto,Ubuntu,Arial}
    a{color:var(--acc);text-decoration:none} a:hover{text-decoration:underline}
    .wrap{max-width:1200px;margin:24px auto;padding:0 16px}
    .head{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:16px}
    h1{font-size:20px;margin:0} .chip{padding:3px 8px;border-radius:999px;background:#0b1220;border:1px solid #1f2937;color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;margin:16px 0}
    .card{background:var(--card);border:1px solid #1f2937;border-radius:12px;padding:14px}
    .card h3{margin:0 0 8px 0;font-size:15px}
    .kv{display:grid;grid-template-columns:140px 1fr;gap:6px 10px}
    .kv div:nth-child(odd){color:var(--muted)}
    .bad{color:var(--ko)} .good{color:var(--ok)} .warn{color:var(--warn)}
    .table-wrap{background:var(--card);border:1px solid #1f2937;border-radius:12px;margin-top:20px;overflow:auto}
    table{width:100%;border-collapse:collapse;font-size:13px;min-width:900px}
    thead{background:#0b1220;color:var(--muted);position:sticky;top:0;z-index:1}
    th,td{padding:10px;border-bottom:1px solid #1f2937;vertical-align:top}
    tr:hover td{background:#0d1526}
    .toolbar{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:10px 0}
    input[type="search"]{background:#0b1220;border:1px solid #1f2937;color:#e5e7eb;border-radius:8px;padding:8px 10px;min-width:260px}
    .muted{color:var(--muted)}
    .pill{display:inline-block;padding:2px 6px;border:1px solid #334155;border-radius:999px;color:#cbd5e1;background:#0b1220}
    .footer{color:var(--muted);margin:16px 0 40px}
    code{background:#0b1220;border:1px solid #1f2937;border-radius:6px;padding:2px 5px}
</style>
</head>
<body>
<div class="wrap">
    <div class="head">
        <h1>🔧 {{ $absPath }}</h1>
        <span class="chip">Laravel {{ $laravel }}</span>
        <span class="chip">PHP {{ $phpVer }}</span>
        <span class="chip">Env: {{ $appEnv }}</span>
        <span class="chip">Debug: {{ $debug }}</span>
        @if($url)<span class="chip">{{ $url }}</span>@endif
    </div>

    <div class="grid">
        <div class="card">
            <h3>Application</h3>
            <div class="kv">
                <div>Nom</div><div>{{ $appName ?? '—' }}</div>
                <div>URL</div><div>{{ $url ?? '—' }}</div>
                <div>Session</div><div><code>{{ $sessionDriver }}</code></div>
                <div>Cache</div><div><code>{{ $cacheDriver }}</code> {!! $cacheOk ? '<span class="good">● OK</span>' : '<span class="bad">● KO</span>' !!}</div>
                <div>Queue</div><div><code>{{ $queueDriver }}</code></div>
                <div>Mail</div><div><code>{{ $mailDriver }}</code></div>
                <div>Storage link</div><div>{!! $storageLinked ? '<span class="good">✓</span>' : '<span class="warn">manquant</span>' !!}</div>
            </div>
        </div>

        <div class="card">
            <h3>Base de données</h3>
            <div class="kv">
                <div>Driver</div><div><code>{{ $dbDriver }}</code></div>
                <div>Connexion</div>
                <div>{!! $dbOk ? '<span class="good">● Connecté</span>' : '<span class="bad">● Échec</span>' !!}
                    @unless($dbOk)
                        <div class="muted" style="margin-top:6px"> {{ $dbErr }} </div>
                    @endunless
                </div>
            </div>
        </div>

        <div class="card">
            <h3>Extensions PHP</h3>
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                @foreach($extensions as $ext => $ok)
                    <span class="pill">{{ $ext }} {!! $ok ? '✓' : '✗' !!}</span>
                @endforeach
            </div>
        </div>

        <div class="card">
            <h3>Composer</h3>
            <div class="kv">
                <div>Packages</div><div>{{ $pkgCount ?? '—' }}</div>
                <div>Exemples</div>
                <div>
                    @if($topPkgs)
                        @foreach($topPkgs as $p)
                            <span class="pill">{{ $p }}</span>
                        @endforeach
                    @else
                        <span class="muted">non disponible</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="toolbar">
        <input id="search" type="search" placeholder="Filtrer routes (URI, name, action, middleware)…">
        <span class="muted">Total routes : <strong id="count">{{ count($routes) }}</strong></span>
    </div>

    <div class="table-wrap">
        <table id="routes">
            <thead>
                <tr>
                    <th style="width:110px">Méthodes</th>
                    <th>URI</th>
                    <th>Nom</th>
                    <th>Action</th>
                    <th>Middleware</th>
                </tr>
            </thead>
            <tbody>
                @foreach($routes as $r)
                    <tr>
                        <td><code>{{ $r['method'] }}</code></td>
                        <td><code>{{ $r['uri'] }}</code></td>
                        <td>{{ $r['name'] ?? '—' }}</td>
                        <td class="muted">{{ $r['action'] }}</td>
                        <td class="muted">{{ $r['middleware'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="footer">
        Astuces rapides : <code>php artisan route:list</code> •
        <code>php artisan config:cache</code> •
        <code>php artisan optimize</code>
    </p>
</div>

<script>
(function(){
    const q = document.getElementById('search');
    const rows = Array.from(document.querySelectorAll('#routes tbody tr'));
    const count = document.getElementById('count');
    function norm(s){ return (s||'').toLowerCase(); }
    function hay(row){
        return row.textContent.toLowerCase();
    }
    q?.addEventListener('input', function(){
        const val = norm(this.value);
        let shown = 0;
        rows.forEach(tr => {
            const on = !val || hay(tr).includes(val);
            tr.style.display = on ? '' : 'none';
            if(on) shown++;
        });
        count.textContent = shown;
    });
})();
</script>
</body>
</html>
