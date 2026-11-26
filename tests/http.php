<?php
declare(strict_types=1);

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'task-test-' . bin2hex(random_bytes(8));
mkdir($root);
mkdir($root . '/public');
mkdir($root . '/src');
copy(__DIR__ . '/../public/index.php', $root . '/public/index.php');
copy(__DIR__ . '/../src/TaskStore.php', $root . '/src/TaskStore.php');
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) {
    throw new RuntimeException("Cannot allocate a test port: $error");
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$log = $root . '/server.log';
$command = [
    PHP_BINARY, '-d', 'extension_dir=' . ini_get('extension_dir'), '-d', 'extension=pdo_sqlite',
    '-S', $address, '-t', $root . '/public',
];
$server = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
if (!is_resource($server)) {
    throw new RuntimeException('Cannot start the PHP test server');
}
fclose($pipes[0]);
$base = 'http://' . $address;
$cookie = '';

function request(string $path, ?array $data = null): array
{
    global $base, $cookie;
    $headers = ['Cookie: ' . $cookie];
    if ($data !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    $context = stream_context_create(['http' => [
        'method' => $data === null ? 'GET' : 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $data === null ? '' : http_build_query($data),
        'ignore_errors' => true,
        'follow_location' => 0,
        'timeout' => 5,
    ]]);
    $body = file_get_contents($base . $path, false, $context);
    if ($body === false) {
        throw new RuntimeException('HTTP request failed: ' . $path);
    }
    foreach ($http_response_header as $header) {
        if (stripos($header, 'Set-Cookie:') === 0) {
            $cookie = explode(';', trim(substr($header, 11)))[0];
        }
    }
    preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0], $match);
    return [(int) $match[1], $body];
}

function verify(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $ready = false;
    for ($i = 0; $i < 50; $i++) {
        $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if ($probe !== false) {
            fclose($probe);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    verify($ready, 'PHP test server did not start');
    [$status, $page] = request('/');
    verify($status === 200 && preg_match('/name="csrf" value="([^"]+)"/', $page, $match) === 1, 'The form provides a CSRF token');
    $token = $match[1];
    [$status] = request('/', ['title' => 'No guardar']);
    verify($status === 403, 'A request without a CSRF token is rejected');
    [$status, $page] = request('/', ['csrf' => $token, 'title' => '   ']);
    verify($status === 422 && str_contains($page, 'título es obligatorio'), 'An empty title is rejected');
    [$status] = request('/', ['csrf' => $token, 'title' => '<script>', 'description' => 'Detalle']);
    verify($status === 303, 'Creating a task redirects');
    [$status, $page] = request('/');
    verify($status === 200 && str_contains($page, 'Tarea creada.'), 'The list confirms task creation');
    verify(str_contains($page, '&lt;script&gt;') && !str_contains($page, '<script>'), 'Task titles are escaped');
    [$status, $page] = request('/?id=1');
    verify($status === 200 && str_contains($page, 'Detalle'), 'The detail page shows the description');
    [$status] = request('/?id=1&edit=1', ['csrf' => $token, 'action' => 'update', 'id' => 1, 'title' => 'Editada', 'description' => 'Cambio']);
    verify($status === 303, 'Updating a task redirects');
    [$status, $page] = request('/?id=1');
    verify($status === 200 && str_contains($page, 'Editada') && str_contains($page, 'Cambio'), 'Updated values appear in detail');
    [$status] = request('/?id=1&delete=1', ['csrf' => $token, 'action' => 'delete', 'id' => 1]);
    verify($status === 303, 'Deleting a task redirects');
    [$status] = request('/?id=1');
    verify($status === 404, 'Deleted task returns 404');
    echo "HTTP tests passed\n";
} finally {
    proc_terminate($server);
    proc_close($server);
    foreach ([$root . '/var/tasks.sqlite', $root . '/public/index.php', $root . '/src/TaskStore.php', $log] as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    if (is_dir($root . '/var')) {
        rmdir($root . '/var');
    }
    rmdir($root . '/public');
    rmdir($root . '/src');
    rmdir($root);
}
