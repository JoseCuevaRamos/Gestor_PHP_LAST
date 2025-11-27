<?php
declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

class SeedRolesTable extends AbstractSeed
{
    public function run(): void
    {
        $data = [
            [
                'id_rol' => 1,
                'nombre' => 'Lider',
                'descripcion' => 'Usuario con permisos de administración y gestión completa del proyecto',
                'status' => '0'
            ],
            [
                'id_rol' => 2, 
                'nombre' => 'Miembro',
                'descripcion' => 'Usuario con permisos básicos para trabajar en el proyecto',
                'status' => '0'
            ]
        ];

        $table = $this->table('roles');
        $table->insert($data)
              ->saveData();
        
        echo "Roles 'Lider' y 'Miembro' insertados correctamente\n";
    }
}