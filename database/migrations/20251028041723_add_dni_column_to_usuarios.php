<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDniColumnToUsuarios extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('usuarios');
        //Si la columna 'dni' ya existe, la eliminamos primero
        if ($table->hasColumn('dni')) {
            $table->removeColumn('dni')->update();
        }
        $table->addColumn('dni', 'string', [
                'limit' => 8,            // DNI: 8 dígitos
                'null' => true,          // permite nulos si aún no se registra
                'after' => 'correo',     // opcional: ubicar después de 'correo'
                'comment' => 'Documento Nacional de Identidad del usuario'
            ])
            ->update();
    }
}
