<?php

namespace App\Services\Ai\Prompts;

use App\Enums\DatabaseDialect;
use App\Enums\TargetFramework;

class DatabasePromptBuilder
{
    /**
     * Membangun system prompt universal untuk pasangan target Framework/ORM dan Database Dialect.
     */
    public static function buildSystemPrompt(
        TargetFramework $framework = TargetFramework::LARAVEL,
        DatabaseDialect $dialect = DatabaseDialect::MYSQL, 
        string $targetVersion = '13'
    ): string {
        $frameworkLabel = $framework->label();
        $dialectLabel = $dialect->label();
        $dialectGuidelines = $dialect->promptGuidelines();
        $frameworkSpecificInstructions = self::getFrameworkInstructions($framework, $dialect, $targetVersion);

        return <<<PROMPT
Anda adalah seorang Principal Software Architect & Database Engineer ahli.
Tugas Anda adalah merancang arsitektur database, diagram ERD Mermaid, dan berkas kode skema ORM lengkap.

TARGET TEKNOLOGI:
- Framework & ORM: {$frameworkLabel}
- Database Dialect: {$dialectLabel}

PANDUAN DIALEK DATABASE ({$dialectLabel}):
{$dialectGuidelines}

PANDUAN SPESIFIK FRAMEWORK & ORM:
{$frameworkSpecificInstructions}

ATURAN OUTPUT:
1. Buat ERD Mermaid yang valid menggunakan sintaks 'erDiagram' dengan atribut tabel dan kardinalitas relasi yang jelas.
2. Buat file-file skema kode lengkap dan siap pakai sesuai ekstensi file target.
3. Seluruh relasi foreign key dan constraint harus terdefinisi dengan benar.
4. Output WAJIB berupa JSON murni valid tanpa markdown pembungkus di luar JSON.

Format JSON:
{
  "erd_mermaid_text": "erDiagram\\n  USERS ||--o{ POSTS : has\\n  USERS {\\n    bigint id PK\\n    string name\\n    string email\\n  }\\n  POSTS {\\n    bigint id PK\\n    bigint user_id FK\\n    string title\\n  }",
  "migration_files": [
    {
      "filename": "nama_file_lengkap_dengan_ekstensi",
      "content": "isi kode skema lengkap"
    }
  ]
}
PROMPT;
    }

    /**
     * Panduan khusus sintaks per ORM/Framework.
     */
    protected static function getFrameworkInstructions(
        TargetFramework $framework, 
        DatabaseDialect $dialect, 
        string $version
    ): string {
        return match ($framework) {
            TargetFramework::LARAVEL => <<<INSTR
- Format: File migrasi Laravel {$version} PHP dengan return anonymous class (return new class extends Migration).
- Setiap tabel berada di file terpisah dengan format penamaan: 'create_{table_name}_table.php'.
- Urutan file: Tabel induk (parent) harus berada sebelum tabel anak (child).
INSTR,

            TargetFramework::EXPRESS_PRISMA => <<<INSTR
- Format: Berikan satu file utama dengan filename: 'schema.prisma'.
- Sintaks Prisma Schema lengkap: datasource db (provider sesuai {$dialect->value}), generator client, dan blok-blok 'model EntityName { ... }'.
- Definisikan atribut @id, @default, @unique, serta relasi @relation(fields: [...], references: [...]) secara lengkap.
INSTR,

            TargetFramework::EXPRESS_DRIZZLE => <<<INSTR
- Format: File TypeScript Drizzle ORM dengan filename: 'schema.ts'.
- Gunakan fungsi pembantu sesuai dialek:
  * PostgreSQL: import { pgTable, serial, text, ... } from 'drizzle-orm/pg-core';
  * MySQL: import { mysqlTable, serial, varchar, ... } from 'drizzle-orm/mysql-core';
  * SQLite: import { sqliteTable, integer, text, ... } from 'drizzle-orm/sqlite-core';
- Ekspor setiap tabel (mis. 'export const users = ...') beserta relasi 'relations()' bila ada.
INSTR,

            TargetFramework::SPRINGBOOT_HIBERNATE => <<<INSTR
- Format: Berikan file Java Entity class terpisah per tabel dengan filename: '{EntityName}.java' (contoh: 'User.java', 'Post.java').
- Gunakan anotasi JPA standar: @Entity, @Table(name = "..."), @Id, @GeneratedValue, @Column, @ManyToOne, @OneToMany, @JoinColumn.
- Sertakan atribut, getter, dan setter standar (atau anotasi Lombok @Data @Entity).
INSTR,

            TargetFramework::RAW_SQL => <<<INSTR
- Format: Berikan file SQL DDL lengkap dengan filename: 'schema.sql'.
- Gunakan sintaks DDL standar sesuai dialek {$dialect->label()}: CREATE TABLE, PRIMARY KEY, FOREIGN KEY, dan index.
INSTR,
        };
    }
}
