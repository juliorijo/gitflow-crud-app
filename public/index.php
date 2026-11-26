<?php
declare(strict_types=1);

require __DIR__ . '/../src/TaskStore.php';
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$directory = __DIR__ . '/../var';
if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
    throw new RuntimeException('No se pudo crear el directorio de datos.');
}
$store = new TaskStore(new PDO('sqlite:' . $directory . '/tasks.sqlite'));
$error = '';
$title = '';
$description = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
        http_response_code(403);
        exit('La sesión del formulario ha caducado. Recarga la página e inténtalo de nuevo.');
    }
    $title = is_string($_POST['title'] ?? null) ? $_POST['title'] : '';
    $description = is_string($_POST['description'] ?? null) ? $_POST['description'] : '';
    try {
        $action = $_POST['action'] ?? 'create';
        if ($action === 'update' || $action === 'delete') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || $id === null) {
                http_response_code(404);
                exit('Tarea no encontrada.');
            }
            $changed = $action === 'update'
                ? $store->update($id, $title, $description)
                : $store->delete($id);
            if (!$changed) {
                http_response_code(404);
                exit('Tarea no encontrada.');
            }
            $destination = $action === 'update' ? '/?id=' . $id : '/';
            $_SESSION['notice'] = $action === 'update' ? 'Cambios guardados.' : 'Tarea eliminada.';
        } elseif ($action === 'create') {
            $store->create($title, $description);
            $destination = '/';
            $_SESSION['notice'] = 'Tarea creada.';
        } else {
            http_response_code(400);
            exit('Acción no válida.');
        }
        header('Location: ' . $destination, true, 303);
        exit;
    } catch (InvalidArgumentException $exception) {
        $error = $exception->getMessage();
        http_response_code(422);
    }
}
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$tasks = $store->all();
$detail = null;
$showDetail = array_key_exists('id', $_GET);
if ($showDetail) {
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $detail = $id === false ? null : $store->find($id);
    if ($detail === null) {
        http_response_code(404);
    }
}
$editing = $detail !== null && isset($_GET['edit']);
$confirmDelete = $detail !== null && isset($_GET['delete']);
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);
if ($editing && $error === '') {
    $title = $detail['title'];
    $description = $detail['description'];
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis tareas</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<main>
    <header>
        <p class="brand">Mis tareas</p>
        <h1>Haz espacio para lo importante.</h1>
        <p>Un lugar sencillo para organizar lo que sigue.</p>
    </header>
    <?php if ($notice !== ''): ?><p class="notice" role="status"><?= escape($notice) ?></p><?php endif; ?>
    <?php if ($showDetail): ?>
    <section class="panel detail">
        <?php if ($detail === null): ?>
            <h2>Tarea no encontrada</h2>
        <?php elseif ($confirmDelete): ?>
            <h2>Eliminar tarea</h2>
            <p>¿Eliminar «<?= escape($detail['title']) ?>»? Esta acción no se puede deshacer.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
                <button type="submit">Eliminar definitivamente</button>
            </form>
            <a href="/?id=<?= (int) $detail['id'] ?>">Cancelar</a>
        <?php elseif ($editing): ?>
            <h2>Editar tarea</h2>
            <?php if ($error !== ''): ?><p class="error" role="alert"><?= escape($error) ?></p><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int) $detail['id'] ?>">
                <label for="title">Título</label>
                <input id="title" name="title" maxlength="120" required value="<?= escape($title) ?>">
                <label for="description">Descripción</label>
                <textarea id="description" name="description" maxlength="5000"><?= escape($description) ?></textarea>
                <button type="submit">Guardar cambios</button>
            </form>
        <?php else: ?>
            <h2><?= escape($detail['title']) ?></h2>
            <p><?= nl2br(escape($detail['description'])) ?></p>
            <p>Creada: <?= escape($detail['created_at']) ?> UTC</p>
            <div class="actions">
                <a href="/?id=<?= (int) $detail['id'] ?>&amp;edit=1">Editar tarea</a>
                <a href="/?id=<?= (int) $detail['id'] ?>&amp;delete=1">Eliminar tarea</a>
            </div>
        <?php endif; ?>
        <a href="/">Volver a la lista</a>
    </section>
    <?php else: ?>
    <div class="workspace">
    <section class="panel">
        <h2>Nueva tarea</h2>
        <p>Empieza por un título. La descripción es opcional.</p>
        <?php if ($error !== ''): ?><p class="error" role="alert"><?= escape($error) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
            <label for="title">Título</label>
            <input id="title" name="title" maxlength="120" required value="<?= escape($title) ?>">
            <label for="description">Descripción</label>
            <textarea id="description" name="description" maxlength="5000"><?= escape($description) ?></textarea>
            <button type="submit">Crear tarea</button>
        </form>
    </section>
    <section class="panel">
        <h2>Lista de tareas <span class="count"><?= count($tasks) ?></span></h2>
        <?php if (!$tasks): ?>
            <p>Aún no hay tareas. Crea la primera con el formulario.</p>
        <?php else: ?>
            <ul class="task-list">
                <?php foreach ($tasks as $task): ?>
                    <li><a href="/?id=<?= (int) $task['id'] ?>"><?= escape($task['title']) ?><span>Ver detalle</span></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    </div>
    <?php endif; ?>
</main>
</body>
</html>
