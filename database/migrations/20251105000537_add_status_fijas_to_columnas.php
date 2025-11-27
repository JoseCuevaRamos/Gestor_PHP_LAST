<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStatusFijasToColumnas extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('columnas');
        
        // Verificar si la columna ya existe manualmente
        $columns = $this->fetchAll('SHOW COLUMNS FROM columnas');
        $statusFijasExists = false;
        
        foreach ($columns as $column) {
            if ($column['Field'] === 'status_fijas') {
                $statusFijasExists = true;
                break;
            }
        }
        
        // Si existe, eliminarla para recrearla correctamente
        if ($statusFijasExists) {
            $this->execute('ALTER TABLE columnas DROP COLUMN status_fijas');
            echo "🗑️  Columna 'status_fijas' eliminada (existía manualmente)\n";
        }
        
        // Agregar columna correctamente con Phinx - SOLO 1 y 2
        $table->addColumn('status_fijas', 'enum', [
            'values' => ['1', '2'],
            'default' => null,
            'null' => true,
            'after' => 'tipo_columna',
            'comment' => '1: progreso, 2: finalizado (solo para columnas fijas)'
        ])->update();
        
        echo "✅ Columna 'status_fijas' agregada correctamente con migración Phinx\n";
    }
}