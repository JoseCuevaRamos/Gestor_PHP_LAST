<?php

use Phinx\Migration\AbstractMigration;

class CreateCfdSnapshotsTable extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('cfd_snapshots');
        $table->addColumn('id_proyecto', 'integer')
              ->addColumn('fecha', 'date')
              ->addColumn('conteo_columnas', 'json')
              ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'datetime', ['null' => true])
              ->addIndex(['id_proyecto', 'fecha'], ['unique' => true])
              ->addForeignKey('id_proyecto', 'proyectos', 'id_proyecto', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION'])
              ->create();
    }

    public function down()
    {
        $this->table('cfd_snapshots')->drop()->save();
    }
}
