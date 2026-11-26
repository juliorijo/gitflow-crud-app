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
        if (($_POST['action'] ?? '') === 'update') {
            $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || $id === null || !$store->update($id, $title, $description)) {
                http_response_code(404);
                exit('Tarea no encontrada.');
            }
            $destination = '/?id=' . $id;
        } else {
            $store->create($title, $description);
            $destination = '/';
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
</head>
<body>
<main>
    <h1>Mis tareas</h1>
    <p>Un espacio sencillo para organizar lo que sigue.</p>
    <?php if ($showDetail): ?>
    <section>
        <?php if ($detail === null): ?>
            <h2>Tarea no encontrada</h2>
        <?php elseif ($editing): ?>
            <h2>Editar tarea</h2>
            <?php if ($error !== ''): ?><p role="alert"><?= escape($error) ?></p><?php endif; ?>
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
            <a href="/?id=<?= (int) $detail['id'] ?>&amp;edit=1">Editar tarea</a>
        <?php endif; ?>
        <a href="/">Volver a la lista</a>
    </section>
    <?php else: ?>
    <section>
        <h2>Nueva tarea</h2>
        <?php if ($error !== ''): ?><p role="alert"><?= escape($error) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= escape($_SESSION['csrf']) ?>">
            <label for="title">Título</label>
            <input id="title" name="title" maxlength="120" required value="<?= escape($title) ?>">
            <label for="description">Descripción</label>
            <textarea id="description" name="description" maxlength="5000"><?= escape($description) ?></textarea>
            <button type="submit">Crear tarea</button>
        </form>
    </section>
    <section>
        <h2>Lista de tareas</h2>
        <?php if (!$tasks): ?>
            <p>Aún no hay tareas. Crea la primera con el formulario.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($tasks as $task): ?>
                    <li><a href="/?id=<?= (int) $task['id'] ?>"><?= escape($task['title']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
