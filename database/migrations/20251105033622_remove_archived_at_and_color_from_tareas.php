<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RemoveArchivedAtAndColorFromTareas extends AbstractMigration
{
    /**
     * Elimina los campos archived_at y color de la tabla tareas
     * Estos campos ya no son necesarios en la lógica del sistema
     */
    public function change(): void
    {
        $table = $this->table('tareas');
        
        // Eliminar el campo archived_at
        if ($table->hasColumn('archived_at')) {
            $table->removeColumn('archived_at');
        }
        
        // Eliminar el campo color
        if ($table->hasColumn('color')) {
            $table->removeColumn('color');
        }
        
        $table->update();
    }
}
