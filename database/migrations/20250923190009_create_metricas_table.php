<?php
use Phinx\Migration\AbstractMigration;
class CreateMetricasTable extends AbstractMigration
{
    public function change()
    {
        $table = $this->table('metricas');
        $table->addColumn('id_proyecto', 'integer', ['null' => false])
              ->addColumn('cycle_time_promedio', 'float', ['null' => false])
              ->addColumn('lead_time_promedio', 'float', ['null' => false])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addColumn('status', 'enum', ['values' => ['0', '1'], 'default' => '0'])
              ->addForeignKey('id_proyecto', 'proyectos', 'id_proyecto')
              ->create();
    }
}
