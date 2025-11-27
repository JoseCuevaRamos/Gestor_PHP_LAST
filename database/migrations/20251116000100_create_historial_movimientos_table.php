<?php

use Phinx\Migration\AbstractMigration;

class CreateHistorialMovimientosTable extends AbstractMigration
{
    public function up()
    {
        $table = $this->table('historial_movimientos');
        $table->addColumn('id_tarea', 'integer')
              ->addColumn('id_columna_anterior', 'integer', ['null' => true]) 
              ->addColumn('id_columna_nueva', 'integer')
              ->addColumn('id_usuario', 'integer', ['null' => true])  
              ->addColumn('timestamp', 'timestamp', ['default' => 'CURRENT_TIMESTAMP']) 
              ->addIndex(['id_tarea', 'timestamp'], ['name' => 'idx_tarea_timestamp'])  
              ->addIndex(['timestamp'], ['name' => 'idx_timestamp']) 
              ->addForeignKey('id_tarea', 'tareas', 'id_tarea', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION'])  // ⭐ CAMBIÉ: tarea → tareas
              ->addForeignKey('id_columna_anterior', 'columnas', 'id_columna', ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'])  // ⭐ CAMBIÉ: columna → columnas
              ->addForeignKey('id_columna_nueva', 'columnas', 'id_columna', ['delete'=> 'CASCADE', 'update'=> 'NO_ACTION'])  // ⭐ CAMBIÉ: columna → columnas
              ->addForeignKey('id_usuario', 'usuarios', 'id_usuario', ['delete'=> 'SET_NULL', 'update'=> 'NO_ACTION'])  // ⭐ CAMBIÉ: usuario → usuarios
              ->create();
    }

    public function down()
    {
        $this->table('historial_movimientos')->drop()->save();
    }
}