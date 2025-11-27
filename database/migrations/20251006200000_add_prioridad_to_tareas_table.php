<?php
use Phinx\Migration\AbstractMigration;

class AddPrioridadToTareasTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('tareas');

        if (!$table->hasColumn('prioridad')) {
            $table->addColumn('prioridad', 'string', [
                'limit' => 20,
                'default' => 'No definido',
                'after' => 'status'
            ])->update();
        }
    }
}
