<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddTipoColumnaToColumnas extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('columnas');
        
        // Verificar si la columna ya existe manualmente
        $columns = $this->fetchAll('SHOW COLUMNS FROM columnas');
        $tipoColumnaExists = false;
        
        foreach ($columns as $column) {
            if ($column['Field'] === 'tipo_columna') {
                $tipoColumnaExists = true;
                break;
            }
        }
        
        // Si existe, eliminarla para recrearla correctamente
        if ($tipoColumnaExists) {
            $this->execute('ALTER TABLE columnas DROP COLUMN tipo_columna');
            echo "🗑️  Columna 'tipo_columna' eliminada (existía manualmente)\n";
        }
        
        // Agregar columna correctamente con Phinx
        $table->addColumn('tipo_columna', 'enum', [
            'values' => ['fija', 'normal'],
            'default' => 'normal',
            'null' => false,
            'after' => 'posicion',
            'comment' => 'fija: columna no eliminable, normal: columna eliminable'
        ])->update();
        
        echo "✅ Columna 'tipo_columna' agregada correctamente con migración Phinx\n"; 
    }
}