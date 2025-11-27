<?php
use Phinx\Migration\AbstractMigration;
class CreateProyectosTable extends AbstractMigration
{
    public function change()
    {
      $table = $this->table('proyectos', ['id' => false, 'primary_key' => ['id_proyecto']]);
      $table->addColumn('id_proyecto', 'integer', ['identity' => true])
          ->addColumn('nombre', 'string', ['limit' => 255])
          ->addColumn('descripcion', 'text', ['null' => true])
          ->addColumn('id_espacio', 'integer', ['null' => false])
          ->addColumn('id_usuario_creador', 'integer', ['null' => false])
          ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
          ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
          ->addColumn('status', 'enum', ['values' => ['0', '1'], 'default' => '0'])
          ->addForeignKey('id_espacio', 'espacios', 'id')
          ->addForeignKey('id_usuario_creador', 'usuarios', 'id_usuario')
          ->create();
    }
}
