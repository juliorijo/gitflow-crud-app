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
echo "Task tests passed\n";
