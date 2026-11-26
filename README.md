# Mis tareas

Aplicación sencilla para organizar tareas con PHP y SQLite.

## Requisitos

- PHP 8.2 o superior con las extensiones `pdo_sqlite` e `iconv`.

## Ejecutar

```sh
php -S localhost:8000 -t public
```

Abre <http://localhost:8000>. La aplicación crea `var/tasks.sqlite` automáticamente; PHP necesita permiso de escritura en el directorio del proyecto. `var/` no se publica ni se incluye en Git.

## Pruebas

```sh
php tests/tasks.php
php tests/http.php
```

La aplicación está diseñada para uso local o personal: no incluye cuentas ni autorización para múltiples usuarios. No la expongas a internet sin añadir protección de acceso.

## Flujo de trabajo

Las ramas permanentes son `dev/developer`, `qa` y `main`. Cada cambio se desarrolla en una rama `feature/` o `hotfix/` y se propone mediante tres pull requests: primero a desarrollo, luego a QA y finalmente a `main`. Antes de cada integración se comprueban las pruebas en GitHub Actions. Las ramas de trabajo se conservan como evidencia del flujo.
