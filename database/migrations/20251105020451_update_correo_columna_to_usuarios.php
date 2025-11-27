<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class UpdateCorreoColumnaToUsuarios extends AbstractMigration
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

        // Solo elimina el índice si existe
        if ($table->hasIndex(['correo'])) {
            $table->removeIndex(['correo'])->update();
        }

        // Opcional: agregar índice normal (no único)
        if (!$table->hasIndex(['correo'])) {
            $table->addIndex(['correo'], ['unique' => false])->update();
        }
    }

    
}
