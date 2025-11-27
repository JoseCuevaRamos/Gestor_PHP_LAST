<?php
use Phinx\Migration\AbstractMigration;
class CreateNotificacionesTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('notificaciones');
        $table->addColumn('id_usuario', 'integer', ['null' => false])
              ->addColumn('mensaje', 'text', ['null' => false])
              ->addColumn('tipo', 'enum', ['values' => ['Tarea Asignada', 'Comentario', 'Cambio de Estado', 'Nuevo Proyecto', 'Otro'], 'null' => false])
              ->addColumn('id_tarea', 'integer', ['null' => true])
              ->addColumn('id_comentario', 'integer', ['null' => true])
              ->addColumn('leida', 'boolean', ['default' => false])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addColumn('status', 'enum', ['values' => ['0', '1'], 'default' => '0'])
              ->addForeignKey('id_usuario', 'usuarios', 'id_usuario')
              ->addForeignKey('id_tarea', 'tareas', 'id_tarea')
              ->addForeignKey('id_comentario', 'comentarios', 'id_comentario')
              ->create();
    }
}
