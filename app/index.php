<?php


declare(strict_types=1);

$config = [
    'host' => getenv('DB_HOST'),
    'port' => getenv('DB_PORT') ?: '3306',
    'name' => getenv('DB_NAME'),
    'user' => getenv('DB_USER'),
    'pass' => getenv('DB_PASSWORD'),
];

$version  = getenv('APP_VERSION') ?: 'sin versión';
$servidor = gethostname() ?: 'desconocido';

$error = null;
$pdo   = null;

$faltantes = [];
foreach (['DB_HOST' => 'host', 'DB_NAME' => 'name', 'DB_USER' => 'user', 'DB_PASSWORD' => 'pass'] as $env => $key) {
    if ($config[$key] === false || $config[$key] === '') {
        $faltantes[] = $env;
    }
}

if ($faltantes) {
    $error = 'Faltan variables de entorno: ' . implode(', ', $faltantes);
} else {
    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4",
            $config['user'],
            $config['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (PDOException $e) {
        $error = 'No se pudo conectar a la base de datos: ' . $e->getMessage();
    }
}

// ---------- Acciones (POST → redirect) ----------
if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);

    try {
        switch ($accion) {
            case 'crear':
                $titulo = trim((string) ($_POST['titulo'] ?? ''));
                if ($titulo !== '') {
                    $stmt = $pdo->prepare('INSERT INTO tareas (titulo) VALUES (:titulo)');
                    $stmt->execute([':titulo' => mb_substr($titulo, 0, 150)]);
                }
                break;

            case 'alternar':
                $stmt = $pdo->prepare('UPDATE tareas SET completada = NOT completada WHERE id = :id');
                $stmt->execute([':id' => $id]);
                break;

            case 'eliminar':
                $stmt = $pdo->prepare('DELETE FROM tareas WHERE id = :id');
                $stmt->execute([':id' => $id]);
                break;
        }
    } catch (PDOException $e) {
        $error = 'Error al ejecutar la acción: ' . $e->getMessage();
    }

    if (!$error) {
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
}

// ---------- Consulta ----------
$tareas = [];
if ($pdo && !$error) {
    try {
        $tareas = $pdo->query(
            'SELECT id, titulo, completada, creada_en
               FROM tareas
           ORDER BY completada ASC, creada_en DESC'
        )->fetchAll();
    } catch (PDOException $e) {
        $error = 'Error al consultar las tareas: ' . $e->getMessage();
    }
}

$pendientes  = count(array_filter($tareas, fn($t) => !$t['completada']));
$completadas = count($tareas) - $pendientes;

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>To-Do Cloud</title>
    <style>
        :root {
            --bg: #f4f5f7; --card: #ffffff; --text: #1d2330; --muted: #6b7385;
            --line: #e3e6ec; --accent: #2f6fde; --ok: #1f9d6b; --danger: #d64545;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg); color: var(--text);
        }
        main { max-width: 640px; margin: 48px auto; padding: 0 16px; }
        h1 { margin: 0 0 4px; font-size: 1.8rem; }
        .sub { color: var(--muted); margin: 0 0 24px; }
        .card {
            background: var(--card); border: 1px solid var(--line);
            border-radius: 12px; padding: 20px;
        }
        form.nueva { display: flex; gap: 8px; margin-bottom: 16px; }
        input[type=text] {
            flex: 1; padding: 10px 12px; border: 1px solid var(--line);
            border-radius: 8px; font-size: 1rem;
        }
        button {
            border: 0; border-radius: 8px; padding: 10px 14px; cursor: pointer;
            font-size: .95rem; background: var(--accent); color: #fff;
        }
        button.sec { background: transparent; color: var(--muted); padding: 6px 8px; }
        button.sec:hover { color: var(--danger); }
        ul { list-style: none; margin: 0; padding: 0; }
        li {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 0; border-top: 1px solid var(--line);
        }
        li .titulo { flex: 1; }
        li.hecha .titulo { text-decoration: line-through; color: var(--muted); }
        li .fecha { color: var(--muted); font-size: .8rem; }
        .check {
            width: 22px; height: 22px; border-radius: 50%; padding: 0;
            border: 2px solid var(--line); background: #fff;
        }
        li.hecha .check { background: var(--ok); border-color: var(--ok); }
        .stats { display: flex; gap: 16px; color: var(--muted); font-size: .9rem; margin-bottom: 8px; }
        .vacio { color: var(--muted); text-align: center; padding: 24px 0; }
        .error {
            background: #fdecec; color: #8a1f1f; border: 1px solid #f5c2c2;
            border-radius: 8px; padding: 12px; margin-bottom: 16px;
        }
        footer {
            margin-top: 16px; color: var(--muted); font-size: .8rem;
            display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px;
        }
        code { background: #eceff4; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
<main>
    <h1>To-Do Cloud</h1>
    <p class="sub">Gestor de tareas del equipo</p>

    <div class="card">
        <?php if ($error): ?>
            <div class="error"><?= e($error) ?></div>
        <?php endif; ?>

        <form class="nueva" method="post">
            <input type="hidden" name="accion" value="crear">
            <input type="text" name="titulo" maxlength="150" placeholder="Nueva tarea…" required>
            <button type="submit">Agregar</button>
        </form>

        <div class="stats">
            <span><?= $pendientes ?> pendiente(s)</span>
            <span><?= $completadas ?> completada(s)</span>
        </div>

        <?php if (!$tareas): ?>
            <p class="vacio">No hay tareas registradas.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($tareas as $t): ?>
                    <li class="<?= $t['completada'] ? 'hecha' : '' ?>">
                        <form method="post">
                            <input type="hidden" name="accion" value="alternar">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button class="check" type="submit" title="Marcar / desmarcar"></button>
                        </form>
                        <span class="titulo"><?= e($t['titulo']) ?></span>
                        <span class="fecha"><?= e(date('d-m-Y H:i', strtotime($t['creada_en']))) ?></span>
                        <form method="post">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button class="sec" type="submit" title="Eliminar">✕</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <footer>
        <span>Versión: <code><?= e($version) ?></code></span>
        <span>Servidor: <code><?= e($servidor) ?></code></span>
        <span>PHP <?= e(PHP_VERSION) ?></span>
    </footer>
</main>
</body>
</html>
