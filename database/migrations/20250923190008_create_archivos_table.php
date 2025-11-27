<?php
use Phinx\Migration\AbstractMigration;
class CreateArchivosTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('archivos');
        $table->addColumn('id_tarea', 'integer', ['null' => false])
              ->addColumn('archivo_nombre', 'string', ['limit' => 255])
              ->addColumn('archivo_ruta', 'string', ['limit' => 255])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addColumn('status', 'enum', ['values' => ['0', '1'], 'default' => '0'])
              ->addForeignKey('id_tarea', 'tareas', 'id_tarea')
              ->create();
    }
}
