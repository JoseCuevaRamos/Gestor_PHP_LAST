<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddColorToTareasTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('tareas');

        if (!$table->hasColumn('color')) {
            $table->addColumn('color', 'string', [
                'null' => true,  // puede quedar vacío
                'after' => 'prioridad', // se ubica justo después de prioridad
            ])->update();
        }
    }
}
