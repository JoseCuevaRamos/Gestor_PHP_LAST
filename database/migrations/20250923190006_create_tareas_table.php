<?php
use Phinx\Migration\AbstractMigration;
class CreateTareasTable extends AbstractMigration
{
    public function change()
    {
      $table = $this->table('tareas', ['id' => false, 'primary_key' => ['id_tarea']]);
      $table->addColumn('id_tarea', 'integer', ['identity' => true])
          ->addColumn('id_proyecto', 'integer', ['null' => false])
          ->addColumn('id_columna', 'integer', ['null' => false])
          ->addColumn('titulo', 'string', ['limit' => 255])
          ->addColumn('descripcion', 'text', ['null' => true])
          ->addColumn('id_creador', 'integer', ['null' => false])
          ->addColumn('id_asignado', 'integer', ['null' => true])
          ->addColumn('position', 'integer', ['null' => true])
          ->addColumn('due_at', 'timestamp', ['null' => true])
          ->addColumn('started_at', 'timestamp', ['null' => true])
          ->addColumn('completed_at', 'timestamp', ['null' => true])
          ->addColumn('archived_at', 'timestamp', ['null' => true])
          ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
          ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
          ->addColumn('status', 'enum', ['values' => ['0', '1'], 'default' => '0'])
          ->addForeignKey('id_proyecto', 'proyectos', 'id_proyecto')
          ->addForeignKey('id_columna', 'columnas', 'id_columna')
          ->addForeignKey('id_creador', 'usuarios', 'id_usuario')
          ->addForeignKey('id_asignado', 'usuarios', 'id_usuario')
          ->create();
    }
}
