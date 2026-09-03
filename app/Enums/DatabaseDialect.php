<?php

namespace App\Enums;

enum DatabaseDialect: string
{
    case MYSQL = 'mysql';
    case POSTGRESQL = 'pgsql';
    case SQLITE = 'sqlite';
    case SQLSERVER = 'sqlsrv';

    public function label(): string
    {
        return match ($this) {
            self::MYSQL => 'MySQL / MariaDB',
            self::POSTGRESQL => 'PostgreSQL (Supabase)',
            self::SQLITE => 'SQLite',
            self::SQLSERVER => 'SQL Server',
        };
    }

    /**
     * Panduan tipe data spesifik untuk prompt AI.
     */
    public function promptGuidelines(): string
    {
        return match ($this) {
            self::POSTGRESQL => <<<GUIDE
- Primary Key: Gunakan \$table->uuid('id')->primary() atau \$table->id()
- Foreign Key: \$table->foreignUuid('xxx_id')->constrained('table_name')->cascadeOnDelete()
- JSON: Gunakan \$table->jsonb('data') untuk optimasi index di PostgreSQL
- Timestamps: Gunakan \$table->timestampsTz() atau \$table->timestamps()
GUIDE,
            self::MYSQL => <<<GUIDE
- Primary Key: Gunakan \$table->id() (bigIncrements) atau \$table->uuid('id')->primary()
- Foreign Key: \$table->foreignId('xxx_id')->constrained('table_name')->cascadeOnDelete()
- JSON: Gunakan \$table->json('data')
- Timestamps: Gunakan \$table->timestamps()
GUIDE,
            self::SQLITE => <<<GUIDE
- Primary Key: Gunakan \$table->id()
- Foreign Key: \$table->foreignId('xxx_id')->constrained('table_name')
- Kolom sederhana: string, integer, boolean, text, timestamps
GUIDE,
            self::SQLSERVER => <<<GUIDE
- Primary Key: Gunakan \$table->id() atau \$table->uuid('id')->primary()
- Foreign Key: \$table->foreignId('xxx_id')->constrained('table_name')
- Tipe data: string, text, integer, boolean, datetime
GUIDE,
        };
    }
}
