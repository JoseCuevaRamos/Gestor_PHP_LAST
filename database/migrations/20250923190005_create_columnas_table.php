<?php
use Phinx\Migration\AbstractMigration;
class CreateColumnasTable extends AbstractMigration
{
    public function change()
    {
      $table = $this->table('columnas', ['id' => false, 'primary_key' => ['id_columna']]);
      $table->addColumn('id_columna', 'integer', ['identity' => true])
          ->addColumn('id_proyecto', 'integer', ['null' => false])
          ->addColumn('nombre', 'string', ['limit' => 255])
          ->addColumn('posicion', 'integer', ['null' => false])
          ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
          ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
          ->addColumn('status', 'enum', ['values' => ['0', '1'], 'default' => '0'])
          ->addForeignKey('id_proyecto', 'proyectos', 'id_proyecto')
          ->create();
    }
}
