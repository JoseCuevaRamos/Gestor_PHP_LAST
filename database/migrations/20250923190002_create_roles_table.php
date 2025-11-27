<?php
use Phinx\Migration\AbstractMigration;
class CreateRolesTable extends AbstractMigration
{
    public function change()
    {
      $table = $this->table('roles', ['id' => false, 'primary_key' => ['id_rol']]);
      $table->addColumn('id_rol', 'integer', ['identity' => true])
          ->addColumn('nombre', 'string', ['limit' => 100])
          ->addColumn('descripcion', 'text', ['null' => true])
          ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
          ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
          ->addColumn('status', 'enum', ['values' => ['0', '1'], 'default' => '0'])
          ->create();
    }
}
