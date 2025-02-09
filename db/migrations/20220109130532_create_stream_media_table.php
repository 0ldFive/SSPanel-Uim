<?php

declare(strict_types=1);

namespace Db\Migrations;

use Phinx\Migration\AbstractMigration;

final class CreateStreamMediaTable extends AbstractMigration
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
        $table = $this->table('stream_media');
        $table->addColumn('node_id', 'integer', ['comment' => '节点id'])
            ->addColumn('result', 'text', ['comment' => '检测结果'])
            ->addColumn('created_at', 'integer', ['comment' => '创建时间'])
            ->create();
    }
}
