<?php
use Phinx\Migration\AbstractMigration;
class CreateComentariosTable extends AbstractMigration
{
    public function change()
    {
      $table = $this->table('comentarios', ['id' => false, 'primary_key' => ['id_comentario']]);
      $table->addColumn('id_comentario', 'integer', ['identity' => true])
          ->addColumn('id_tarea', 'integer', ['null' => false])
          ->addColumn('id_usuario', 'integer', ['null' => false])
          ->addColumn('contenido', 'text', ['null' => false])
          ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
          ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
          ->addColumn('status', 'enum', ['values' => ['0', '1'], 'default' => '0'])
          ->addForeignKey('id_tarea', 'tareas', 'id_tarea')
          ->addForeignKey('id_usuario', 'usuarios', 'id_usuario')
          ->create();
    }
}
