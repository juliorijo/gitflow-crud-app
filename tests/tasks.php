<?php
declare(strict_types=1);

$file = __DIR__ . '/../src/TaskStore.php';
if (is_file($file)) {
    require $file;
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

check(class_exists('TaskStore'), 'TaskStore must be available');
$store = new TaskStore(new PDO('sqlite::memory:'));
check($store->all() === [], 'A new task list is empty');
$id = $store->create('  Preparar entrega  ', 'Revisar el proyecto');
$tasks = $store->all();
check(count($tasks) === 1 && $tasks[0]['title'] === 'Preparar entrega', 'Created tasks appear with trimmed titles');
check((int) $tasks[0]['id'] === $id, 'Created task has its returned ID');
try {
    $store->create('   ', '');
    throw new RuntimeException('An empty title was accepted');
} catch (InvalidArgumentException $exception) {
    check($exception->getMessage() !== '', 'Validation explains the error');
}
check(method_exists($store, 'find'), 'Tasks can be retrieved individually');
$task = $store->find($id);
check($task !== null && $task['description'] === 'Revisar el proyecto', 'Task detail includes its description');
check($store->find(999) === null, 'A missing task has no detail');
check(method_exists($store, 'update'), 'Tasks can be updated');
check($store->update($id, '  Nueva tarea  ', 'Actualizada'), 'An existing task can be updated');
check($store->find($id)['title'] === 'Nueva tarea', 'The updated title is saved');
check(!$store->update(999, 'Ausente', ''), 'Updating a missing task reports failure');
try {
    $store->update($id, ' ', '');
    throw new RuntimeException('An empty updated title was accepted');
} catch (InvalidArgumentException $exception) {
    check($store->find($id)['title'] === 'Nueva tarea', 'Invalid edits do not change the stored task');
}
check(method_exists($store, 'delete'), 'Tasks can be deleted');
check(!$store->delete(999), 'Deleting a missing task reports failure');
check($store->delete($id), 'An existing task can be deleted');
check($store->find($id) === null && $store->all() === [], 'Deleted tasks no longer appear');
echo "Task tests passed\n";
