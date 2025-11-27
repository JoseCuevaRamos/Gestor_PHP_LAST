<?php
use Phinx\Migration\AbstractMigration;
class CreateEspaciosTable extends AbstractMigration
{
    public function change()
    {
      $table = $this->table('espacios', ['id' => false, 'primary_key' => ['id']]);
      $table->addColumn('id', 'integer', ['identity' => true])
          ->addColumn('nombre', 'string', ['limit' => 120])
          ->addColumn('descripcion', 'text', ['null' => true])
          ->addColumn('id_usuario', 'integer', ['null' => false])
          ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
          ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
          ->addColumn('status', 'enum', ['values' => ['0', '1'], 'default' => '0'])
          ->addForeignKey('id_usuario', 'usuarios', 'id_usuario')
          ->create();
    }
}
