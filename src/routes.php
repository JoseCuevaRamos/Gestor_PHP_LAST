<?php

use Conduit\Controllers\Comentario\ComentarioController;
use Conduit\Services\PhotosDrive\GoogleDriveService;
use Conduit\Controllers\Auth\LoginController;
use Conduit\Controllers\Auth\RegisterController;
use Conduit\Controllers\User\UserController;
use Conduit\Controllers\Espacio\EspacioController;
use Conduit\Controllers\Proyecto\ProyectoController;
use Conduit\Controllers\Columna\ColumnaController;
use Conduit\Controllers\Perfil\PerfilController;
use Conduit\Controllers\Tarea\TareaController;
use Conduit\Controllers\Archivo\ArchivoController;
use Conduit\Middleware\OptionalAuth;
use Slim\Http\Request;
use Slim\Http\Response;

// ======================== API ROUTES ========================
$app->group('/api', 
    function () {
        $jwtMiddleware = $this->getContainer()->get('jwt');
        $optionalAuth = $this->getContainer()->get('optionalAuth');

        $validateJwt = $this->getContainer()->get('validateJwt');

    /** ======================= USUARIOS ======================= */
    $this->post('/usuarios/temporal', UserController::class . ':createTempUser');

    // Auth
    $this->post('/users', RegisterController::class . ':register')->setName('auth.register');
    $this->post('/users/login', LoginController::class . ':login')->setName('auth.login');
    $this->put('/users/actualizarTemporal', LoginController::class . ':actualizarTemporal')->setName('auth.login');

    // Usuario autenticado
    $this->get('/users', UserController::class . ':index')->add($optionalAuth)->setName('user.index');
    $this->get('/users/{id}', UserController::class . ':show')->add($optionalAuth)->setName('user.show');
    //Busqueda de usuario por correo
    $this->post('/users/search-by-email', UserController::class . ':searchByEmail')->setName('user.searchByEmail');
    $this->post('/users/validate-dni-correo', UserController::class . ':validateDniCorreo')->setName('user.validateDniCorreo');
    //Actualización y eliminación de usuario
    $this->put('/users/update', UserController::class . ':update')->setName('user.update');
    $this->delete('/users/{id}', UserController::class . ':destroy')->add($validateJwt)->setName('user.destroy');

    // Actualización de perfil
    $this->put('/perfil/updatePerfil/{id_usuario}', PerfilController::class . ':updatePerfil')->add($validateJwt)->setName('perfil.updatePerfil');

    // Búsqueda de usuario
    $this->get('/usuarios/buscar', UserController::class . ':search')->add($optionalAuth)->setName('user.search');

    /** ======================= ESPACIOS ======================= */
    $this->get('/users/{id}/espacios', EspacioController::class . ':index')->add($optionalAuth)->setName('espacio.index');
    $this->get('/espacios/{id}', EspacioController::class . ':show')->add($optionalAuth)->setName('espacio.show');
    $this->post('/espacios', EspacioController::class . ':store')->add($validateJwt)->setName('espacio.store');
    $this->put('/espacios/{id}', EspacioController::class . ':update')->add($validateJwt)->setName('espacio.update');
    $this->delete('/espacios/{id}', EspacioController::class . ':destroy')->add($validateJwt)->setName('espacio.destroy');

    /** ======================= PROYECTOS ======================= */
    $this->get('/espacios/{id}/proyectos', ProyectoController::class . ':index')->add($validateJwt)->setName('proyecto.index');
    $this->get('/proyectos/{id}', ProyectoController::class . ':show')->add($validateJwt)->setName('proyecto.show');
    $this->post('/proyectos', ProyectoController::class . ':store')->add($validateJwt)->setName('proyecto.store');
    $this->put('/proyectos/{id}', ProyectoController::class . ':update')->add($validateJwt)->setName('proyecto.update');
    $this->delete('/proyectos/{id}', ProyectoController::class . ':destroy')->add($validateJwt)->setName('proyecto.destroy');
    $this->post('/proyectos/{id_proyecto}/miembros', ProyectoController::class . ':agregarMiembro')->add($validateJwt)->setName('proyecto.agregarMiembro');
    $this->get('/proyectos/{id_proyecto}/miembros', ProyectoController::class . ':obtenerMiembros')->add($validateJwt)->setName('proyecto.obtenerMiembros');
    $this->delete('/proyectos/{id_proyecto}/miembros/{id_usuario}', ProyectoController::class . ':quitarMiembro')->add($validateJwt)->setName('proyecto.quitarMiembro');
    $this->delete('/proyectos/{id_proyecto}/abandonar', ProyectoController::class . ':abandonarProyecto')->add($validateJwt)->setName('proyecto.abandonarProyecto');
    $this->put('/proyectos/{id_proyecto}/miembros/{id_usuario}/rol', ProyectoController::class . ':cambiarRol')->add($validateJwt)->setName('proyecto.cambiarRol');
    /** ======================= TAREAS ======================= */
    // Rutas básicas CRUD
    $this->get('/tareas', TareaController::class . ':index')->add($optionalAuth)->setName('tarea.index');
    $this->post('/tareas', TareaController::class . ':store')->add($validateJwt)->setName('tarea.store');
    $this->get('/tareas/{id}', TareaController::class . ':show')->add($optionalAuth)->setName('tarea.show');
    $this->put('/tareas/{id}', TareaController::class . ':update')->add($validateJwt)->setName('tarea.update');
    $this->delete('/tareas/{id}', TareaController::class . ':destroy')->add($validateJwt)->setName('tarea.destroy');

    // Operaciones PATCH individuales
    $this->map(['PATCH'], '/tareas/{id}/move', TareaController::class . ':move')->add($validateJwt)->setName('tarea.move');
    $this->map(['PATCH'], '/tareas/{id}/assign', TareaController::class . ':assign')->add($validateJwt)->setName('tarea.assign');
    $this->map(['PATCH'], '/tareas/{id}/set-due', TareaController::class . ':setDue')->add($validateJwt)->setName('tarea.setDue');
    $this->map(['PATCH'], '/tareas/{id}/start', TareaController::class . ':start')->add($validateJwt)->setName('tarea.start');
    $this->map(['PATCH'], '/tareas/{id}/complete', TareaController::class . ':complete')->add($validateJwt)->setName('tarea.complete');

    // Operaciones en lote (bulk)
    $this->post('/tareas/bulk/reorder', TareaController::class . ':bulkReorder')->add($validateJwt)->setName('tarea.bulkReorder');
    $this->post('/tareas/bulk/move', TareaController::class . ':bulkMove')->add($validateJwt)->setName('tarea.bulkMove');

    // Resumen por proyecto
    $this->get('/proyectos/{id_proyecto}/tareas/resumen', TareaController::class . ':resumenPorProyecto')->add($optionalAuth)->setName('tarea.resumenPorProyecto');

    // Métodos HEAD para optimización
    $this->map(['HEAD'], '/tareas', TareaController::class . ':headIndex');
    $this->map(['HEAD'], '/tareas/{id}', TareaController::class . ':head');

    /** ======================= COLUMNAS ======================= */
    $this->get('/proyectos/{id}/columnas', ColumnaController::class . ':index')->add($validateJwt)->setName('columna.index');
    $this->get('/columnas/{id}', ColumnaController::class . ':show')->add($optionalAuth)->setName('columna.show');
    $this->post('/columnas', ColumnaController::class . ':store')->setName('columna.store')->add($validateJwt)->setName('columna.store');
    //$this->post('/columnas', ColumnaController::class . ':store')->setName('columna.store');
    $this->put('/columnas/{id}', ColumnaController::class . ':update')->setName('columna.update')->add($validateJwt)->setName('columna.update');
    $this->delete('/columnas/{id}', ColumnaController::class . ':destroy')->add($validateJwt)->setName('columna.destroy');
    $this->put('/proyectos/{id}/columnas/gestionar-tipos', ColumnaController::class . ':gestionarTipos')->add($validateJwt)->setName('columna.gestionar-tipos');   
    /** ======================= ARCHIVOS ======================= */
    $this->get('/tareas/{id}/archivos', ArchivoController::class . ':index')->add($optionalAuth)->setName('archivo.index');
    $this->get('/archivos/{id}', ArchivoController::class . ':show')->add($optionalAuth)->setName('archivo.show');
    //$this->post('/archivos', ArchivoController::class . ':store')->setName('archivo.store');
    $this->post('/archivos', ArchivoController::class . ':store')->add($validateJwt)->setName('archivo.store');
    $this->put('/archivos/{id}', ArchivoController::class . ':update')->add($validateJwt)->setName('archivo.update');
    $this->delete('/archivos/{id}', ArchivoController::class . ':eliminar')->add($validateJwt)->setName('archivo.eliminar');

    /** ======================= COMENTARIOS ======================= */
    $this->get('/tareas/{id_tarea}/comentarios', ComentarioController::class . ':list')->add($optionalAuth)->setName('comentario.list');
    $this->post('/comentarios', ComentarioController::class . ':create')->add($validateJwt)->setName('comentario.create');
    $this->get('/comentarios/{id}', ComentarioController::class . ':view')->add($optionalAuth)->setName('comentario.view');
    $this->put('/comentarios/{id}', ComentarioController::class . ':update')->add($validateJwt)->setName('comentario.update');
    $this->delete('/comentarios/{id}', ComentarioController::class . ':delete')->add($validateJwt)->setName('comentario.delete');
    $this->get('/proyectos/{id}/cfd', ProyectoController::class . ':cfd')->add($optionalAuth)->setName('proyecto.cfd');
});

// ======================== RUTA PRINCIPAL ========================
$app->get('/[{name}]', function (Request $request, Response $response, array $args) {
    $this->logger->info("Slim-Skeleton '/' route");
    return $this->renderer->render($response, 'index.phtml', $args);
});
