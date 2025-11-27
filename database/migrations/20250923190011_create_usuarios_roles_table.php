<?php
use Phinx\Migration\AbstractMigration;
class CreateUsuariosRolesTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('usuarios_roles');
        $table->addColumn('id_usuario', 'integer', ['null' => false])
              ->addColumn('id_rol', 'integer', ['null' => false])
              ->addColumn('id_espacio', 'integer', ['null' => true])
              ->addColumn('id_proyecto', 'integer', ['null' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addColumn('status', 'enum', ['values' => ['0', '1'], 'default' => '0'])
              ->addForeignKey('id_usuario', 'usuarios', 'id_usuario')
              ->addForeignKey('id_rol', 'roles', 'id_rol')
              ->addForeignKey('id_espacio', 'espacios', 'id')
              ->addForeignKey('id_proyecto', 'proyectos', 'id_proyecto')
              ->create();
    }
}
