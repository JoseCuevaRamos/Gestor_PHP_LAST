<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddColorToColumnas extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('columnas');
        
        // Verificar y eliminar columna si existe
        $columns = $this->fetchAll('SHOW COLUMNS FROM columnas');
        $colorExists = false;
        
        foreach ($columns as $column) {
            if ($column['Field'] === 'color') {
                $colorExists = true;
                break;
            }
        }
        
        if ($colorExists) {
            $this->execute('ALTER TABLE columnas DROP COLUMN color');
            echo "🗑️  Columna 'color' eliminada (existía manualmente)\n";
        }
        
        // Agregar columna correctamente con Phinx
        $table->addColumn('color', 'text', [
            'null' => true, 
            'after' => 'nombre'
        ])->update();
        
        echo "✅ Columna 'color' agregada correctamente con migración Phinx\n";
    }
}