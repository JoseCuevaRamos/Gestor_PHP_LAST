<?php
use Phinx\Migration\AbstractMigration;
class CreateUsuariosTable extends AbstractMigration
{
    public function change()
    {
      $table = $this->table('usuarios', ['id' => false, 'primary_key' => ['id_usuario']]);
      $table->addColumn('id_usuario', 'integer', ['identity' => true])
          ->addColumn('nombre', 'string', ['limit' => 255])
          ->addColumn('correo', 'string', ['limit' => 255])
          ->addColumn('password_hash', 'string', ['limit' => 255])
          ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
          ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
          ->addColumn('status', 'enum', ['values' => ['0', '1'], 'default' => '0'])
          ->addIndex(['correo'], ['unique' => true])
          ->create();
    }
}
