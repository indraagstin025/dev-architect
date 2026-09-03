<?php

namespace App\Enums;

enum TargetFramework: string
{
    case LARAVEL = 'laravel';
    case EXPRESS_PRISMA = 'express_prisma';
    case EXPRESS_DRIZZLE = 'express_drizzle';
    case SPRINGBOOT_HIBERNATE = 'springboot_hibernate';
    case RAW_SQL = 'raw_sql';

    /**
     * Label representasi untuk antarmuka pengguna.
     */
    public function label(): string
    {
        return match ($this) {
            self::LARAVEL => 'Laravel (Eloquent Migrations)',
            self::EXPRESS_PRISMA => 'Express.js (Prisma ORM)',
            self::EXPRESS_DRIZZLE => 'Express.js (Drizzle ORM)',
            self::SPRINGBOOT_HIBERNATE => 'Java Spring Boot (Hibernate/JPA)',
            self::RAW_SQL => 'Universal (Raw SQL DDL)',
        };
    }

    /**
     * Direktori default tempat meletakkan file skema/migrasi di dalam proyek.
     */
    public function defaultInjectionPath(): string
    {
        return match ($this) {
            self::LARAVEL => 'database/migrations',
            self::EXPRESS_PRISMA => 'prisma',
            self::EXPRESS_DRIZZLE => 'src/db',
            self::SPRINGBOOT_HIBERNATE => 'src/main/java/com/example/demo/model',
            self::RAW_SQL => '.',
        };
    }

    /**
     * Ekstensi file kode skema yang dihasilkan.
     */
    public function fileExtension(): string
    {
        return match ($this) {
            self::LARAVEL => 'php',
            self::EXPRESS_PRISMA => 'prisma',
            self::EXPRESS_DRIZZLE => 'ts',
            self::SPRINGBOOT_HIBERNATE => 'java',
            self::RAW_SQL => 'sql',
        };
    }
}
