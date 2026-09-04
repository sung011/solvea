<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * 문서 v3의 크롤링 공통 데이터 계약 테이블.
 *
 * 기존 API 캐시 테이블(program 등)은 변경하지 않는다.
 */
class CreateCrawlingTables extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'source_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'source_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'source_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'local',
            ],
            'list_url' => [
                'type'       => 'TEXT',
                'null'       => true,
            ],
            'crawl_method' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'http',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'last_collected_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addPrimaryKey('source_id');
        $this->forge->createTable('crawl_source', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'source_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'source_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'source_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'local',
            ],
            'list_url' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'original_url' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
            ],
            'posted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'apply_start' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'apply_end' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'body_text' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'attachment_urls' => [
                'type' => 'TEXT',
                'null' => true,
                'comment' => 'JSON array of attachment URLs',
            ],
            'organizer' => [
                'type'       => 'VARCHAR',
                'constraint' => 300,
                'null'       => true,
            ],
            'operator' => [
                'type'       => 'VARCHAR',
                'constraint' => 300,
                'null'       => true,
            ],
            'contact' => [
                'type'       => 'VARCHAR',
                'constraint' => 300,
                'null'       => true,
            ],
            'region' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'raw_html_or_json' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'collected_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'min_age' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'null'       => true,
            ],
            'max_age' => [
                'type'       => 'SMALLINT',
                'constraint' => 5,
                'null'       => true,
            ],
            'residence_condition' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'employment_condition' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'student_condition' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'business_condition' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'income_condition' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'benefit' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'youth_relevance' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => true,
            ],
            'source_dup_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'ai_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'pending',
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('source_id');
        $this->forge->addKey('region');
        $this->forge->addKey('apply_end');
        $this->forge->addUniqueKey('source_dup_key');
        $this->forge->addKey(['source_id', 'original_url']);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('crawl_notice', true);

        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'notice_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
            ],
            'file_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
            ],
            'file_url' => [
                'type' => 'TEXT',
            ],
            'file_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('notice_id');
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('crawl_attachment', true);
    }

    public function down()
    {
        $this->forge->dropTable('crawl_attachment', true);
        $this->forge->dropTable('crawl_notice', true);
        $this->forge->dropTable('crawl_source', true);
    }
}
